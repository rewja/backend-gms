<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Admin bypass
        if ($user->role === 'admin_ga') {
            return $next($request);
        }

        // If generic 'user' is allowed
        if (in_array('user', $roles, true) && $user->role === 'user') {
            return $next($request);
        }

        // Category-based roles for users (ob/driver/security)
        $categoryRoles = ['ob', 'driver', 'security'];
        $requestedCategoryRoles = array_intersect($roles, $categoryRoles);
        if ($user->role === 'user' && !empty($requestedCategoryRoles)) {
            if ($user->category && in_array($user->category, $requestedCategoryRoles, true)) {
                return $next($request);
            }
        }

        // Fallback direct role matching (e.g., procurement routes)
        if (in_array($user->role, $roles, true)) {
            return $next($request);
        }

        return response()->json(['message' => 'Unauthorized.'], 403);
    }
}
