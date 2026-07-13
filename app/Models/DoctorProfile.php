<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DoctorProfile Model
 *
 * بيانات العرض المهنية للطبيب (biography, qualifications, fees...).
 * مُنفصَل عن Doctor لأسباب الأداء (Phase 1 analysis - مشكلة #9/#12).
 *
 * @property int         $id
 * @property int         $doctor_id
 * @property string|null $biography
 * @property array|null  $qualifications  ← JSON
 * @property float|null  $consultation_fee
 * @property string|null $photo_path
 * @property array|null  $languages       ← JSON
 */
class DoctorProfile extends Model
{
    protected $fillable = [
        'doctor_id',
        'biography',
        'qualifications',
        'consultation_fee',
        'photo_path',
        'languages',
    ];

    protected function casts(): array
    {
        return [
            'qualifications'   => 'array',
            'languages'        => 'array',
            'consultation_fee' => 'float',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
