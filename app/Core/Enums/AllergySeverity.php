<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum AllergySeverity: string
{
    use EnumValues;

    case Mild     = 'mild';
    case Moderate = 'moderate';
    case Severe   = 'severe';
}
