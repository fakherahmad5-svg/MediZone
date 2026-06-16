<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum PaymentStatus: string
{
    use EnumValues;

    case Pending   = 'pending';
    case Completed = 'completed';
    case Failed    = 'failed';
    case Refunded  = 'refunded';
}
