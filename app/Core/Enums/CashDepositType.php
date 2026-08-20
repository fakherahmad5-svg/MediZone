<?php

namespace App\Core\Enums;

use App\Core\Enums\Concerns\EnumValues;

enum CashDepositType: string
{
    use EnumValues;

    case Percentage = 'percentage';
    case Fixed      = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'Percentage',
            self::Fixed      => 'Fixed Amount',
        };
    }

    public function isPercentage(): bool
    {
        return $this === self::Percentage;
    }

    public function isFixed(): bool
    {
        return $this === self::Fixed;
    }
}