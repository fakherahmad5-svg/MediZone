<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum WaitlistStatus: string
{
    use EnumValues;

    case Waiting   = 'waiting';
    case Notified  = 'notified';
    case Booked    = 'booked';
    case Cancelled = 'cancelled';
    case Expired   = 'expired';
}
