<?php

namespace App\Core\Exceptions;

class DomainValidationException extends AppException
{
    public function __construct(
        string $message = 'Validation failed',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errors);
    }
}
