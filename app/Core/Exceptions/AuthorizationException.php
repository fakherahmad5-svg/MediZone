<?php

namespace App\Core\Exceptions;

class AuthorizationException extends AppException
{
    public function __construct(
        string $message = 'You do not have permission to perform this action.',
        array $errors = []
    ) {
        parent::__construct($message, 403, $errors);
    }
}
