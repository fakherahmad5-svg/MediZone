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


class AuthorizationException extends AppException
{
    public function __construct(
        string $message = 'You do not have permission to perform this action.',
        array $errors = []
    ) {
        parent::__construct($message, 403, $errors);
    }
}


class BusinessException extends AppException
{
    public function __construct(
        string $message = 'Business rule violation',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errors);
    }
}


class ConflictException extends AppException
{
    public function __construct(
        string $message = 'Resource conflict',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errors);
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
