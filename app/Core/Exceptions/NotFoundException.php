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
