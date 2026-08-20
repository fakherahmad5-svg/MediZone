<?php

namespace App\Core\Exceptions;

use Exception;


class DuplicateRefundException extends CancellationException
{
    public static function forPayment(int $paymentId): self
    {
        return new self("Payment #{$paymentId} already has a pending or completed refund — refusing to process a duplicate.");
    }
}