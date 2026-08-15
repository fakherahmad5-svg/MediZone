<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;


enum ConsultationType: string
{
    use EnumValues;

    case Chat     = 'chat';
    case InPerson = 'in_person';
}
