<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum ReceptionistsStatus: string
{
    use EnumValues;

    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Suspended = 'suspended';
}
