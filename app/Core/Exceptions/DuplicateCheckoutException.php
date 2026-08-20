<?php

namespace App\Core\Exceptions;

class DuplicateCheckoutException extends AppException
{
    public function __construct(
        string $message = 'A checkout session already exists for this appointment.',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errors);
    }

    public static function forAppointment(int $appointmentId): self
    {
        return new self("Appointment #{$appointmentId} already has a pending or completed checkout session.");
    }
}