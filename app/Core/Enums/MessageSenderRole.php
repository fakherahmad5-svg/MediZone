<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum MessageSenderRole: string
{
    use EnumValues;

    case Patient = 'patient';
    case Doctor  = 'doctor';
    case System  = 'system';
}
