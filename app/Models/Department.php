<?php

namespace App\Models;

use App\Models\Doctor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'description',
    ];

    public function clinics(): BelongsToMany
    {
        return $this->belongsToMany(Clinic::class, 'clinic_departments')
            ->withTimestamps();
    }


    public function doctors(): BelongsToMany
    {
        return $this->belongsToMany(Doctor::class, 'doctor_departments')
            ->withPivot(['clinic_id', 'is_primary'])
            ->withTimestamps();
    }

    public function doctorDepartments(): HasMany
    {
        return $this->hasMany(DoctorDepartment::class);
    }
}
