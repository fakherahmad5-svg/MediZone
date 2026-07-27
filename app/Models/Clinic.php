<?php

namespace App\Models;

use App\Core\Enums\ClinicStatus;
use App\Models\ClinicUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Clinic extends Model implements HasMedia
{
    use SoftDeletes,InteractsWithMedia;
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'latitude'  => 'float',
            'longitude' => 'float',
            'status'    => ClinicStatus::class,
        ];
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
    public function departments(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Department::class, 'clinic_departments');
    }

    public function clinicUsers(): HasMany
    {
        return $this->hasMany(ClinicUser::class);
    }

    public function doctorDepartments(): HasMany
    {
        return $this->hasMany(DoctorDepartment::class);
    }

    // ─── Status Helpers ───────────────────────────────────────────

    public function isPending(): bool
    {
        return $this->status === ClinicStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === ClinicStatus::Active;
    }

    public function isRejected(): bool
    {
        return $this->status === ClinicStatus::Rejected;
    }

    public function isSuspended(): bool
    {
        return $this->status === ClinicStatus::Suspended;
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('license')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'application/pdf'])
            ->singleFile()
            ->useDisk('public');
    }

    public function licenseUrl(): ?string
    {
        return $this->getFirstMediaUrl('license') ?: null;
    }
}
