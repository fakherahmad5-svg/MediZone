<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum AppointmentStatus: string
{
    use EnumValues;

    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow    = 'no_show';
}
