<?php

namespace App\Core\Enums\Concerns;


trait EnumValues
{
    public static function values(): array
    {
        return array_map(fn ($case) => $case->value, self::cases());
    }

    public static function labels(): array
    {
        return array_combine(
            self::values(),
            array_map(
                fn ($case) => str($case->value)->replace('_', ' ')->title()->toString(),
                self::cases()
            )
        );
    }
}
