<?php

namespace App\Core\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;


class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }


        $userRole = $user->role ?? null;

        if (! in_array($userRole, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Insufficient role permissions.',
                'required_roles' => $roles,
            ], 403);
        }

        return $next($request);
    }
}
