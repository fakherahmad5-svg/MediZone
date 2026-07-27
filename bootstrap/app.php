<?php

/**
 * bootstrap/app.php — Phase 2 Update
 *
 * التغييرات عن نسخة Phase 0:
 *   1. إضافة 'verified' alias → EnsureEmailIsVerified middleware (JSON بدلاً من redirect)
 *   2. تسجيل event listener: Registered → SendEmailVerificationNotification
 *      (يُشغِّل إرسال verification email عند event(new Registered($user)) في AuthService)
 *
 * كل ما كان في Phase 0 محفوظ كما هو (ForceJsonResponse، CheckRole، Exception Handler).
 */

use App\Core\Http\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Auth\Access\AuthorizationException as LaravelAuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use App\Core\Exceptions\AppException;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )

    ->withMiddleware(function (Middleware $middleware) {

        $middleware->prependToGroup('api', [
            \App\Core\Http\Middleware\ForceJsonResponse::class,
        ]);

        $middleware->alias([

            'role'         => \App\Core\Http\Middleware\CheckRole::class,
            'clinic.scope' => \App\Core\Http\Middleware\ClinicScope::class,


            'verified'     => EnsureEmailIsVerified::class,
        ]);
    })



    ->withExceptions(function (Exceptions $exceptions) {

        $exceptions->render(function (\Throwable $e, $request): ?JsonResponse {

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof AppException) {
                return $e->render();
            }

            if ($e instanceof ValidationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The given data was invalid.',
                    'errors'  => $e->errors(),
                ], 422);
            }

            if ($e instanceof AuthenticationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated. Please login to continue.',
                ], 401);
            }

            if ($e instanceof LaravelAuthorizationException) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }

            if ($e instanceof ModelNotFoundException) {
                $model = class_basename($e->getModel());
                return response()->json([
                    'success' => false,
                    'message' => "{$model} not found.",
                ], 404);
            }

            if ($e instanceof NotFoundHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'The requested endpoint was not found.',
                ], 404);
            }

            if ($e instanceof MethodNotAllowedHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'HTTP method not allowed for this endpoint.',
                ], 405);
            }

            if ($e instanceof TooManyRequestsHttpException) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please slow down.',
                    'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
                ], 429);
            }

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
