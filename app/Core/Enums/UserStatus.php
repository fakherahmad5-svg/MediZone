<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum UserStatus: string
{
    use EnumValues;

    case Active   = 'active';
    case Inactive = 'inactive';
    case Banned   = 'banned';
}
