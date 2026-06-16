<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum NotificationStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Sent    = 'sent';
    case Failed  = 'failed';
    case Read    = 'read';
}
