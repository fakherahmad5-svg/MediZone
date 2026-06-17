<?php

namespace App\Core\Exceptions;

class BusinessException extends AppException
{
    public function __construct(
        string $message = 'Business rule violation',
        array $errors = []
    ) {
        parent::__construct($message, 422, $errors);
    }
}
