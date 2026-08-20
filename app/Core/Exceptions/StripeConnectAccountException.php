<?php

namespace App\Core\Exceptions;

class StripeConnectAccountException extends AppException
{
    public static function fromStripeError(string $message): self
    {
        return new self("Stripe Connect account operation failed: {$message}");
    }

    public function __construct(
        string $message = 'A Stripe Connect account operation failed.',
        array $errors = []
    ) {
        parent::__construct($message, 503, $errors);
    }
}
