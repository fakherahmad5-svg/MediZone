<?php

namespace App\Core\Traits;

use App\Core\Exceptions\NotFoundException;
use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\DoctorDepartment;


trait VerifiesDoctorClinicLink
{

    protected function ensureDoctorLinkedToClinic(Doctor $doctor, int $clinicId): Clinic
    {
        $clinic = Clinic::find($clinicId);

        if (! $clinic) {
            throw new NotFoundException('Clinic not found.');
        }

        $linked = DoctorDepartment::where('doctor_id', $doctor->id)
            ->where('clinic_id', $clinicId)
            ->exists();

        if (! $linked) {
            throw new NotFoundException('This doctor is not linked to this clinic.');
        }

        return $clinic;
    }
}
