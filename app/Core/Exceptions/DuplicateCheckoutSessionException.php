<?php

namespace App\Core\Exceptions;

class DuplicateCheckoutSessionException extends AppException
{
    public static function forAppointment(int $appointmentId): self
    {
        return new self("Appointment #{$appointmentId} already has an active or completed payment — refusing to create a duplicate checkout session.");
    }

    public function __construct(
        string $message = 'A payment already exists for this appointment.',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errors);
    }
}
