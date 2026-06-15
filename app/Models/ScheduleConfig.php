<?php

namespace App\Models;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScheduleConfig extends Model
{
    protected $fillable = [
        'clinic_id',
        'doctor_id',
        'consultation_duration',
        'break_duration',
        'max_patients',
        'buffer_enabled',
        'is_vacation_mode',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'buffer_enabled'   => 'boolean',
            'is_vacation_mode' => 'boolean',
            'is_active'        => 'boolean',
        ];
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function days(): HasMany
    {
        return $this->hasMany(ScheduleDay::class);
    }
}