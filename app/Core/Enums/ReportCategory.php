<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum ReportCategory: string
{
    use EnumValues;

    case Misconduct       = 'misconduct';
    case Negligence       = 'negligence';
    case Fraud            = 'fraud';
    case VerbalAbuse      = 'verbal_abuse';
    case PrivacyViolation = 'privacy_violation';
    case Other            = 'other';
}
