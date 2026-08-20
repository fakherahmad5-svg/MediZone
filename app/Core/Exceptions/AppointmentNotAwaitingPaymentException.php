<?php

namespace App\Core\Exceptions;

class AppointmentNotAwaitingPaymentException extends AppException
{
    public function __construct(
        string $message = 'This appointment is not awaiting payment.',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errors);
    }

    public static function forAppointment(int $appointmentId): self
    {
        return new self("Appointment #{$appointmentId} is not awaiting payment and cannot start a new checkout session.");
    }
}