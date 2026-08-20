<?php

namespace App\Core\Exceptions;

class AppointmentAlreadyPastException extends CancellationException
{
    public static function make(): self
    {
        return new self('Cannot cancel an appointment whose scheduled time has already passed.');
    }
}