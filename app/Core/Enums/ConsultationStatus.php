<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum ConsultationStatus: string
{
    use EnumValues;

    case Waiting   = 'waiting';
    case Active    = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Missed    = 'missed';
}
