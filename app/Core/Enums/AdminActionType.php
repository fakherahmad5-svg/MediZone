<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum AdminActionType: string
{
    use EnumValues;

    case Warning    = 'warning';
    case Suspension = 'suspension';
    case Dismissal  = 'dismissal';
    case NoAction   = 'no_action';
    case Escalated  = 'escalated';
}
