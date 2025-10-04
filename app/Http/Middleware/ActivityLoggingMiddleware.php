<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\ActivityService;
use Illuminate\Support\Facades\Auth;

class ActivityLoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
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
    private function shouldSkipLogging($routeName, $method)
    {
        $skipRoutes = [
            'activities.mine',
            'activities.index',
            'activities.stats',
            'me',
        ];

        return in_array($routeName, $skipRoutes) || $method === 'GET';
    }

    /**
     * Determine the action based on HTTP method and route.
     */
    private function determineAction($method, $routeName)
    {
        switch ($method) {
            case 'POST':
                if (str_contains($routeName, 'approve')) return 'approve';
                if (str_contains($routeName, 'reject')) return 'reject';
                return 'create';
            case 'PUT':
            case 'PATCH':
                return 'update';
            case 'DELETE':
                return 'delete';
            default:
                return null;
        }
    }

    /**
     * Generate a human-readable description for the activity.
     */
    private function generateDescription($method, $routeName, Request $request)
    {
        if (!$routeName) {
            return "Performed {$method} request";
        }

        $resource = $this->extractResource($routeName);
        $action = $this->determineAction($method, $routeName);

        switch ($action) {
            case 'create':
                return "Created new {$resource}";
            case 'update':
                return "Updated {$resource}";
            case 'delete':
                return "Deleted {$resource}";
            case 'approve':
                return "Approved {$resource}";
            case 'reject':
                return "Rejected {$resource}";
            default:
                return "Performed action on {$resource}";
        }
    }

    /**
     * Extract resource name from route name.
     */
    private function extractResource($routeName)
    {
        $parts = explode('.', $routeName);
        $resource = $parts[0] ?? 'item';
        
        // Map route names to friendly resource names
        $resourceMap = [
            'users' => 'user',
            'todos' => 'todo',
            'requests' => 'request',
            'assets' => 'asset',
            'meetings' => 'meeting',
            'visitors' => 'visitor',
            'procurements' => 'procurement',
        ];

        return $resourceMap[$resource] ?? $resource;
    }
}
