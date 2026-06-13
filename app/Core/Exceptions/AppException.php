<?php

namespace App\Core\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;


abstract class AppException extends Exception
{
    protected int $statusCode;
    protected array $errors;

    public function __construct(
        string $message = '',
        int $statusCode = 400,
        array $errors = [],
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $statusCode, $previous);
        $this->statusCode = $statusCode;
        $this->errors     = $errors;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function render(): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $this->getMessage(),
        ];

        if (! empty($this->errors)) {
            $response['errors'] = $this->errors;
        }

        return response()->json($response, $this->statusCode);
    }
}
