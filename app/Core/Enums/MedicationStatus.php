<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum MedicationStatus: string
{
    use EnumValues;

    case Active    = 'active';
    case Completed = 'completed';
    case Stopped   = 'stopped';
    case OnHold    = 'on_hold';
}
