<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum AppointmentStatus: string
{
    use EnumValues;

    case Scheduled  = 'scheduled';
    case CheckedIn  = 'checked_in';
    case InProgress = 'in_progress';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
    case NoShow     = 'no_show';


    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }
}
