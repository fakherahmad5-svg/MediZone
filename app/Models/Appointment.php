<?php

namespace App\Models;

use App\Core\Enums\AppointmentStatus;
use App\Core\Enums\ConsultationType;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'patient_id',
        'doctor_id',
        'slot_id',
        'status',
        'encounter_type',
        'price',
        'cancellation_reason',
        'created_by',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'status'          => AppointmentStatus::class,
            'encounter_type'  => ConsultationType::class,
            'price'           => 'float',
        ];
    }
    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(DoctorTimeSlot::class, 'slot_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function encounter(): HasOne
    {
        return $this->hasOne(Encounter::class);
    }

    public function consultation(): HasOne
    {
        return $this->hasOne(Consultation::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    // ─── Status Helpers ───────────────────────────────────────────

    public function isScheduled(): bool
    {
        return $this->status === AppointmentStatus::Scheduled;
    }

    public function isCheckedIn(): bool
    {
        return $this->status === AppointmentStatus::CheckedIn;
    }

    public function isInProgress(): bool
    {
        return $this->status === AppointmentStatus::InProgress;
    }

    public function isCompleted(): bool
    {
        return $this->status === AppointmentStatus::Completed;
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    public function isActive(): bool
    {
        return ! $this->status->isTerminal();
    }
}
