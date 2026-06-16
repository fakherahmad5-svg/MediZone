<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum MedicationSource: string
{
    use EnumValues;

    case SelfReported  = 'self_reported';
    case Prescribed    = 'prescribed';
    case DoctorRecorded = 'doctor_recorded';
}
