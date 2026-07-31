<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;
enum MedicationRoute: string
{
    use EnumValues;
    case ORAL = 'oral';
    case IV = 'iv';
    case IM = 'im';
    case SUBCUTANEOUS = 'subcutaneous';
    case INHALATION = 'inhalation';
    case TOPICAL = 'topical';
    case RECTAL = 'rectal';
    case NASAL = 'nasal';
    case OPHTHALMIC = 'ophthalmic';
    case OTIC = 'otic';
    case TRANSDERMAL = 'transdermal';
}
