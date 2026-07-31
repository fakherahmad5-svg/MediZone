<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum MessageType: string
{
    use EnumValues;

    case Text      = 'text';
    case Image     = 'image';
    case File      = 'file';
    case System    = 'system';
}
