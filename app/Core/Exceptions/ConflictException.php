<?php

namespace App\Core\Exceptions;

class ConflictException extends AppException
{
    public function __construct(
        string $message = 'Resource conflict',
        array $errors = []
    ) {
        parent::__construct($message, 409, $errors);
    }
}
