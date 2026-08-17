<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoctorClinic extends Model
{
    protected $fillable = [
        'doctor_id',
        'clinic_id',
        'consultation_fee',
    ];

    protected function casts(): array
    {
        return [
            'consultation_fee' => 'decimal:2',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
