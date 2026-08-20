<?php

namespace App\Core\Exceptions;

use Exception;

class AppointmentNotCancellableException extends CancellationException
{
    public static function forStatus(string $status): self
    {
        return new self("Appointment cannot be cancelled from its current status: {$status}.");
    }
}