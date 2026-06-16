<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum Gender: string
{
    use EnumValues;

    case Male   = 'male';
    case Female = 'female';
}
