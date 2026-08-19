<?php

namespace App\Core\Exceptions;

class ServiceUnavailableException extends AppException
{
    public function __construct(
        string $message = 'Service temporarily unavailable. Please try again later.',
        array $errors = []
    ) {
        parent::__construct($message, 503, $errors);
    }
}
