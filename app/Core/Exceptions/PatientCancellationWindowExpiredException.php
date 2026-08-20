<?php

namespace App\Core\Exceptions;

class PatientCancellationWindowExpiredException extends CancellationException
{
    public static function make(): self
    {
        return new self('Patient cancellations are not allowed within 2 hours of the appointment. Please contact the clinic directly.');
    }
}
