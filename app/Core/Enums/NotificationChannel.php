<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum NotificationChannel: string
{
    use EnumValues;

    case InApp = 'in_app';
    case Push  = 'push';
    case Email = 'email';
    case Sms   = 'sms';
}
