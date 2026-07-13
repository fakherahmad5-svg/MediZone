<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum ReceptionistsStatus: string
{
    use EnumValues;

    case Pending   = 'pending';
    case Verified  = 'verified';
    case Rejected  = 'rejected';
    case Suspended = 'suspended';
}
