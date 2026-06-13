<?php


use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use App\Core\Exceptions\AppException;

return Application::configure(basePath: dirname(__DIR__))

    // ─── Routing Configuration ────────────────────────────────────────
    ->withRouting(
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )

    // ─── Middleware Configuration ─────────────────────────────────────
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->prependToGroup('api', [
            \App\Core\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([
            'role'         => \App\Core\Http\Middleware\CheckRole::class,
            'clinic.scope' => \App\Core\Http\Middleware\ClinicScope::class,
        ]);
    })

    // ─── Exception Handling ───────────────────────────────────────────
    ->withExceptions(function (Exceptions $exceptions) {


        $exceptions->render(function (\Throwable $e, $request): ?JsonResponse {

            // default handler
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            // ── 1. Custom AppExceptions ──────────────

            if ($e instanceof AppException) {
                return $e->render();
            }

            // ── 2. Validation Errors ───────────────────────────────────

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            // ── 3. Unauthenticated ─────────────────────────────────────

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login to continue.',
                ], 401);
            }

            // ── 4. Unauthorized (Policy/Gate failure) ──────────────────

            if ($e instanceof LaravelAuthorizationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            // ── 5. Model Not Found ─────────────────────────────────────

            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "{$model} not found.",
                ], 404);
            }

            // ── 6. Route Not Found ─────────────────────────────────────
            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested endpoint was not found.',
                ], 404);
            }

            // ── 7. Method Not Allowed ──────────────────────────────────
            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method not allowed for this endpoint.',
                ], 405);
            }

            // ── 8. Rate Limit Exceeded ─────────────────────────────────
            if ($e instanceof TooManyRequestsHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429);
            }

            // ── 9. Unexpected Server Error ─────────────────────────────
            return response()->json([
                'success' => false,
                'message' => app()->isProduction()
                    ? 'An unexpected error occurred. Please try again later.'
                    : $e->getMessage(),
                'debug' => app()->isProduction() ? null : [
                    'exception' => get_class($e),
                    'file'      => $e->getFile(),
                    'line'      => $e->getLine(),
                ],
            ], 500);
        });
    })

    ->create();
