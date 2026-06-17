<?php

namespace App\Core\Http\Middleware;

use App\Modules\Auth\Services\UserRoleService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function __construct(
        private readonly UserRoleService $userRoleService,
    ) {}

    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        if (! $this->userRoleService->hasRole($user, ...$roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Insufficient role permissions.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
