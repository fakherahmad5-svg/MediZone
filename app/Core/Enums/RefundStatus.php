<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum RefundStatus: string
{
    use EnumValues;

    case Pending    = 'pending';
    case Succeeded  = 'succeeded';
    case Failed     = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Succeeded, self::Failed], true);
    }
}