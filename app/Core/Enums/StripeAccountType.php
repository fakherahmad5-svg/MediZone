<?php

namespace App\Core\Enums;

enum StripeAccountType: string
{
    case Express = 'express';
    case Standard = 'standard';

    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}