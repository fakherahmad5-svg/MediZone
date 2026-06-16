<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum AccessStatus: string
{
    use EnumValues;

    case Active  = 'active';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
