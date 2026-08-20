<?php

namespace App\Core\Exceptions;

class DoctorNotStripeReadyException extends AppException
{
    public function __construct(
        string $message = 'This doctor cannot receive online payments yet.',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errors);
    }

    public static function forDoctor(int $doctorId): self
    {
        return new self("Doctor #{$doctorId} has not completed Stripe Connect onboarding and cannot receive online payments.");
    }
}