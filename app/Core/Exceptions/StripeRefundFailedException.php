<?php

namespace App\Core\Exceptions;

use Exception;

class StripeRefundFailedException extends CancellationException
{
    public static function fromStripeError(string $message): self
    {
        return new self("Stripe refund failed: {$message}");
}

}