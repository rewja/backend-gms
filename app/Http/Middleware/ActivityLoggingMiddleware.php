<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ActivityService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ActivityLoggingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only log for authenticated users and successful responses
        if (Auth::check() && $response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->logActivity($request, $response);
        }

        return $response;
    }

    /**
     * Log activity based on the request method and route.
     */
    private function logActivity(Request $request, $response)
    {
        $user = Auth::user();
        $method = $request->method();
        $route = $request->route();
        $routeName = $route ? $route->getName() : null;

        // Skip logging for certain routes
        if ($this->shouldSkipLogging($routeName, $method)) {
            return;
        }

        $action = $this->determineAction($method, $routeName);
        $description = $this->generateDescription($method, $routeName, $request);

        if ($action && $description) {
            ActivityService::log(
                $user->id,
                $action,
                $description,
                null,
                null,
                null,
                null,
                $request
            );
        }
    }

    /**
     * Determine if we should skip logging for this route.
     */
    private function shouldSkipLogging(?string $routeName, string $method): bool
    {
        // Skip logging for these routes
        $skipRoutes = [
            'api.activities.index',
            'api.activities.mine',
            'api.activities.stats',
            'api.activities.export',
        ];

        // Skip GET requests to certain routes
        $skipGetRoutes = [
            'api.activities',
            'api.dashboard',
            'api.profile',
        ];

        if (in_array($routeName, $skipRoutes)) {
            return true;
        }

        if ($method === 'GET' && in_array($routeName, $skipGetRoutes)) {
            return true;
        }

        return false;
    }

    /**
     * Determine the action based on HTTP method and route.
     */
    private function determineAction(string $method, ?string $routeName): ?string
    {
        // Map HTTP methods to actions
        $methodActions = [
            'POST' => 'create',
            'PUT' => 'update',
            'PATCH' => 'update',
            'DELETE' => 'delete',
        ];

        // Special cases for specific routes
        if ($routeName) {
            if (str_contains($routeName, 'login')) {
                return 'login';
            }
            if (str_contains($routeName, 'logout')) {
                return 'logout';
            }
            if (str_contains($routeName, 'export')) {
                return 'export';
            }
            if (str_contains($routeName, 'import')) {
                return 'import';
            }
        }

        return $methodActions[$method] ?? null;
    }

    /**
     * Generate a human-readable description for the activity.
     */
    private function generateDescription(string $method, ?string $routeName, Request $request): ?string
    {
        if (!$routeName) {
            return null;
        }

        // Extract resource name from route
        $resource = $this->extractResourceName($routeName);
        
        // Generate description based on action
        $action = $this->determineAction($method, $routeName);
        
        switch ($action) {
            case 'create':
                return "Created new {$resource}";
            case 'update':
                return "Updated {$resource}";
            case 'delete':
                return "Deleted {$resource}";
            case 'export':
                return "Exported {$resource} data";
            case 'import':
                return "Imported {$resource} data";
            default:
                return "Accessed {$resource}";
        }
    }

    /**
     * Extract resource name from route name.
     */
    private function extractResourceName(string $routeName): string
    {
        // Remove common prefixes
        $routeName = str_replace(['api.', 'admin.'], '', $routeName);
        
        // Extract the main resource
        $parts = explode('.', $routeName);
        $resource = $parts[0] ?? 'resource';
        
        // Convert to human readable
        $resource = str_replace('_', ' ', $resource);
        $resource = ucwords($resource);
        
        return $resource;
    }
}


