<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum PaymentMethod: string
{
    use EnumValues;

    case Cash   = 'cash';
    case Card   = 'card';
    case Online = 'online'; // شام كاش عبر API Syria (Read-Only Integration)
}
