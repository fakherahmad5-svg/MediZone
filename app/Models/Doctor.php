<?php


namespace App\Models;

use App\Core\Enums\DoctorVerificationStatus;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Doctor extends Model implements HasMedia
{
    use SoftDeletes , InteractsWithMedia;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'verification_status' => DoctorVerificationStatus::class,
            'practice_start_date'    => 'date'
        ];
    }

    public function profile(): HasOne
    {
        return $this->hasOne(DoctorProfile::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departments(): BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'doctor_departments')
            ->withPivot(['clinic_id', 'is_primary'])
            ->withTimestamps();
    }

    public function doctorDepartments(): HasMany
    {
        return $this->hasMany(DoctorDepartment::class);
    }



    /**
     * [NEW] العيادات الفريدة التي ينتمي إليها الطبيب — مُشتقَّة من
     * doctor_departments.clinic_id (قد يعمل بعدة عيادات).
     */
    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'doctor_departments')
            ->select('clinics.*')
            ->distinct();
    }


    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function scheduleConfigs(): HasMany
    {
        return $this->hasMany(ScheduleConfig::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(DoctorTimeSlot::class);
    }

    public function blockedTimes(): HasMany
    {
        return $this->hasMany(BlockedTime::class);
    }

    public function patientAccesses(): HasMany
    {
        return $this->hasMany(PatientDoctorAccess::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(DoctorReview::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(DoctorFavorite::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(DoctorReport::class);
    }

    public function clinicalNotes(): HasMany
    {
        return $this->hasMany(ClinicalNote::class);
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    protected function experienceYears(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->practice_start_date
                ? (int) $this->practice_start_date->diffInYears(now())
                : null,
        );
    }


    // ─── Status Helpers ───────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->verification_status === DoctorVerificationStatus::Pending;
    }

    public function isVerified(): bool
    {
        return $this->verification_status === DoctorVerificationStatus::Verified;
    }

    public function isRejected(): bool
    {
        return $this->verification_status === DoctorVerificationStatus::Rejected;
    }

    public function isSuspended(): bool
    {
        return $this->verification_status === DoctorVerificationStatus::Suspended;
    }

    // ─── Media Helpers ────────────────────────────────────────────

    public function photoUrl(): ?string
    {
        return $this->getFirstMediaUrl('photo') ?: null;
    }

    public function licenseFileUrl(): ?string
    {
        return $this->getFirstMediaUrl('license') ?: null;
    }

    public function idCardUrl(): ?string
    {
        return $this->getFirstMediaUrl('id_card') ?: null;
    }

    /** @return array<int, string> */
    public function certificateUrls(): array
    {
        return $this->getMedia('certificates')->map->getUrl()->all();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('certificates')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->useDisk('public');

        $this->addMediaCollection('license')
        ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->singleFile()
            ->useDisk('public');

        $this->addMediaCollection('id_card')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->useDisk('public');

        $this->addMediaCollection('photo')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->useDisk('public');
    }
}
