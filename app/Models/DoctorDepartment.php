<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * DoctorDepartment Pivot Model
 *
 * الحالة: [NEW - Phase 3]
 * مكان الملف: app/Models/DoctorDepartment.php
 *
 * جدول doctor_departments أُنشئ في Phase 1 وتُكتَب إليه صفوف مباشرة
 * عبر DB::table('doctor_departments')->insert(...) في Auth.zip
 * (RegistrationService::completeDoctorProfile). هذا الـ Model يُضفي
 * عليه واجهة Eloquent نظيفة تستخدمها خدمات Phase 3
 * (DoctorService::joinDepartment, ClinicService::doctorsInClinic...)
 * دون كسر ما يكتبه Auth.zip (نفس الجدول، نفس الأعمدة تماماً).
 *
 * @property int  $doctor_id
 * @property int  $department_id
 * @property int  $clinic_id
 * @property bool $is_primary
 */
class DoctorDepartment extends Model
{
    protected $table = 'doctor_departments';

    protected $fillable = [
        'doctor_id',
        'department_id',
        'clinic_id',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }
}
