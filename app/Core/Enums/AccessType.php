<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum AccessType: string
{
    use EnumValues;

    case ReadOnly = 'read_only';
    case Full     = 'full';
}
