<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum PaymentStatus: string
{
    use EnumValues;
  
    case Unpaid             = 'unpaid';
    case Pending            = 'pending';
    case Paid               = 'paid';
    case PartiallyPaid      = 'partially_paid';
    case Refunded           = 'refunded';
    case PartiallyRefunded  = 'partially_refunded';
    case Failed             = 'failed';

    
    public function isTerminal(): bool
    {
        return in_array($this, [self::Paid, self::Refunded, self::Failed], true);
    }

  
    public function isRefundable(): bool
    {
        return in_array($this, [self::Paid, self::PartiallyPaid], true);
    }
}
 
