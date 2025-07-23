<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        // Process the request
        $response = $next($request);

        // Log the request after processing
        $this->logRequest($request, $response, $startTime);

        return $response;
    }

    private function logRequest(Request $request, Response $response, float $startTime): void
    {
        // Skip logging for certain routes to avoid noise
        $skipRoutes = [
            'livewire/update',
            'livewire/upload-file',
            '_debugbar',
            'telescope',
        ];

        foreach ($skipRoutes as $skipRoute) {
            if (str_contains($request->getPathInfo(), $skipRoute)) {
                return;
            }
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        $action = $this->determineAction($request);
        $severity = $this->determineSeverity($request, $response);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'resource_type' => null,
            'resource_id' => null,
            'old_values' => [],
            'new_values' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $duration,
                'user_agent' => $request->userAgent(),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'session_id' => session()->getId(),
            'severity' => $severity,
            'description' => "HTTP {$request->method()} request to {$request->getPathInfo()}",
        ]);
    }

    private function determineAction(Request $request): string
    {
        $method = $request->method();
        $path = $request->getPathInfo();

        if (str_contains($path, '/login')) {
            return 'login_attempt';
        }

        if (str_contains($path, '/logout')) {
            return 'logout';
        }

        if (str_contains($path, '/password')) {
            return match ($method) {
                'GET' => 'view_password',
                'POST' => 'create_password',
                'PUT', 'PATCH' => 'update_password',
                'DELETE' => 'delete_password',
                default => 'password_action'
            };
        }

        return match ($method) {
            'GET' => 'view_page',
            'POST' => 'create_resource',
            'PUT', 'PATCH' => 'update_resource',
            'DELETE' => 'delete_resource',
            default => 'http_request'
        };
    }

    private function determineSeverity(Request $request, Response $response): string
    {
        $statusCode = $response->getStatusCode();

        // Critical for authentication failures and server errors
        if ($statusCode >= 500) {
            return 'critical';
        }

        // High for authentication issues
        if ($statusCode === 401 || $statusCode === 403) {
            return 'high';
        }

        // Medium for client errors
        if ($statusCode >= 400) {
            return 'medium';
        }

        // High for sensitive operations
        if (str_contains($request->getPathInfo(), '/password') &&
            in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            return 'high';
        }

        return 'low';
    }
}
