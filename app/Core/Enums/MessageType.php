<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum MessageType: string
{
    use EnumValues;

    case Text      = 'text';
    case Image     = 'image';
    case File      = 'file';
    case VoiceNote = 'voice_note';
    case System    = 'system';
}
