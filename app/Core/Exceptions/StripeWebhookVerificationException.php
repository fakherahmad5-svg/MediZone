<?php

namespace App\Core\Exceptions;

class StripeWebhookVerificationException extends AppException
{
    public function __construct(
        string $message = 'Unable to verify webhook signature.',
        array $errors = []
    ) {
        parent::__construct($message, 400, $errors);
    }

    public static function fromError(string $message): self
    {
        return new self("Stripe webhook signature verification failed: {$message}");
    }
}