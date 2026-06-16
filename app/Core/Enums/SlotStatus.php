<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum SlotStatus: string
{
    use EnumValues;

    case Available = 'available';
    case Booked    = 'booked';
    case Blocked   = 'blocked';
}
