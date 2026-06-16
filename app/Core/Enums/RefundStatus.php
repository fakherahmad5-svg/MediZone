<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum RefundStatus: string
{
    use EnumValues;

    case Pending   = 'pending';
    case Approved  = 'approved';
    case Rejected  = 'rejected';
    case Completed = 'completed';
}
