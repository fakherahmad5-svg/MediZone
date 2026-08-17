<?php

namespace App\Models;

use App\Core\Enums\AccessStatus;
use App\Core\Enums\AccessType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PatientDoctorAccess extends Model
{
    protected $fillable = [
        'patient_id',
        'doctor_id',
        'granted_by',
        'access_type',
        'appointment_id',
        'status',
        'expires_at',
        'granted_at',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'access_type' => AccessType::class,
            'status'      => AccessStatus::class,
            'expires_at'  => 'datetime',
            'granted_at'  => 'datetime',
            'revoked_at'  => 'datetime',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(MedicalRecordAccessLog::class, 'access_id');
    }
    public function appointment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\Appointment::class);
    }
}
