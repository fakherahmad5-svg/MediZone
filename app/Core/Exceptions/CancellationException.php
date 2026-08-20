<?php

namespace App\Core\Exceptions;

abstract class CancellationException extends AppException
{
    public function __construct(
        string $message,
        int $statusCode = 409,
        array $errors = []
    ) {
        parent::__construct($message, $statusCode, $errors);
    }
}