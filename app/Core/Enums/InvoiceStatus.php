<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum InvoiceStatus: string
{
    use EnumValues;

    case Pending       = 'pending';
    case Paid          = 'paid';
    case PartiallyPaid = 'partially_paid';
    case Cancelled     = 'cancelled';
}
