<?php

namespace App\Core\Exceptions;

class StripeCheckoutFailedException extends AppException
{
    public function __construct(
        string $message = 'Unable to start checkout at this time.',
        array $errors = []
    ) {
        parent::__construct($message, 503, $errors);
    }

    public static function fromStripeError(string $message): self
    {
        return new self("Stripe checkout session creation failed: {$message}");
    }
}