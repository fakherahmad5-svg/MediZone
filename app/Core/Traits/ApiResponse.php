<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

trait ApiResponse
{

    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation successful',
        int $statusCode = 200,
        array $meta = []
    ): JsonResponse {

        $response = [
            'success' => true,
            'message' => $message,
        ];

        if (! is_null($data)) {
            $response['data'] = ($data instanceof JsonResource || $data instanceof ResourceCollection)
                ? $data->resolve(request())
                : $data;
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }


    protected function createdResponse(
        mixed $data = null,
        string $message = 'Resource created successfully'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }


    protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, 204);
    }


    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully',
        ?\Closure $dataTransformer = null
    ): JsonResponse {

        $items = $paginator->items();

        if ($dataTransformer) {
            $items = array_map($dataTransformer, $items);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $items,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], 200);
    }


    protected function errorResponse(
        string $message = 'An error occurred',
        int $statusCode = 400,
        array $errors = []
    ): JsonResponse {

        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }


    protected function notFoundResponse(
        string $message = 'Resource not found'
    ): JsonResponse {
        return $this->errorResponse($message, 404);
    }


    protected function unauthorizedResponse(
        string $message = 'Unauthenticated. Please login to continue.'
    ): JsonResponse {
        return $this->errorResponse($message, 401);
    }


    protected function forbiddenResponse(
        string $message = 'You do not have permission to perform this action.'
    ): JsonResponse {
        return $this->errorResponse($message, 403);
    }


    protected function validationErrorResponse(
        array $errors,
        string $message = 'Validation failed'
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'errors'  => $errors,
        ], 422);
    }


    protected function conflictResponse(
        string $message = 'Resource conflict',
        array $errors = []
    ): JsonResponse {
        return $this->errorResponse($message, 409, $errors);
    }


    protected function serverErrorResponse(
        string $message = 'An unexpected error occurred. Please try again later.'
    ): JsonResponse {
        return $this->errorResponse($message, 500);
    }
}
