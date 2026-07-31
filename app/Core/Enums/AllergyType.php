<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum AllergyType: string
{
    use EnumValues;

    case Food     = 'food';
    case Drug = 'drug';
    case Environment   = 'environment';
    case Insect = 'Insect';
    case Latex = 'latex';
    case Chemical = 'chemical';
    case Cosmetic = 'cosmetic';
    case Other = 'other';

}

