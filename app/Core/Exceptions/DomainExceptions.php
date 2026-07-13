<?php

namespace App\Core\Exceptions;

class NotFoundException extends AppException
{
    public function __construct(
        string $message = 'Resource not found',
        array $errors = []
    ) {
        parent::__construct($message, 404, $errors);
    }
}



class DomainValidationException extends AppException
{
    public function __construct(
        string $message = 'Validation failed',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errors);
    }
}

class ServiceUnavailableException extends AppException
{
    public function __construct(
        string $message = 'Service temporarily unavailable. Please try again later.',
        array $errors = []
    ) {
        parent::__construct($message, 503, $errors);
    }
}
