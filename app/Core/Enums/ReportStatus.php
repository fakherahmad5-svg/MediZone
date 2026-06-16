<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum ReportStatus: string
{
    use EnumValues;

    case Pending      = 'pending';
    case UnderReview  = 'under_review';
    case ActionTaken  = 'action_taken';
    case Resolved     = 'resolved';
    case Dismissed    = 'dismissed';
}
