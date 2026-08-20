<?php

namespace App\Core\Exceptions;

use Exception;

class AppointmentMissingSlotException extends CancellationException
{
    public static function forAppointment(int $appointmentId): self
    {
        return new self("Appointment #{$appointmentId} has no assigned slot — cannot determine time until appointment.");
    }
}