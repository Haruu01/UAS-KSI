<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SecurityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Rate limiting check
        if ($this->isRateLimited($request)) {
            AuditLog::log(
                'rate_limit_exceeded',
                null,
                [],
                ['ip' => $request->ip(), 'user_agent' => $request->userAgent()],
                'high',
                'Rate limit exceeded for IP: ' . $request->ip()
            );

            return response()->json(['error' => 'Too many requests'], 429);
        }

        // Check for suspicious patterns
        $this->checkSuspiciousActivity($request);

        $response = $next($request);

        // Add security headers
        $this->addSecurityHeaders($response);

        return $response;
    }

    private function isRateLimited(Request $request): bool
    {
        $ip = $request->ip();
        $key = "rate_limit:{$ip}";

        $attempts = Cache::get($key, 0);

        if ($attempts >= 100) { // 100 requests per minute
            return true;
        }

        Cache::put($key, $attempts + 1, 60); // 1 minute

        return false;
    }

    private function checkSuspiciousActivity(Request $request): void
    {
        // Skip checking for Livewire requests to avoid false positives
        if (str_contains($request->getPathInfo(), 'livewire')) {
            return;
        }

        $suspiciousPatterns = [
            // SQL Injection patterns (more specific)
            '/(\bunion\s+select\b|\bselect\s+.*\s+from\b|\binsert\s+into\b|\bdelete\s+from\b|\bdrop\s+table\b)/i',
            // XSS patterns
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/i',
            // Path traversal
            '/\.\.[\/\\\\]/',
            // Command injection (exclude common JSON characters)
            '/[;&|`]/i',
        ];

        $input = json_encode($request->all());
        $url = $request->fullUrl();
        $userAgent = $request->userAgent();

        foreach ($suspiciousPatterns as $pattern) {
            if (preg_match($pattern, $input) ||
                preg_match($pattern, $url) ||
                preg_match($pattern, $userAgent)) {

                AuditLog::log(
                    'suspicious_activity_detected',
                    null,
                    [],
                    [
                        'pattern' => $pattern,
                        'input' => substr($input, 0, 500), // Limit input length
                        'url' => $url,
                        'user_agent' => $userAgent,
                    ],
                    'high', // Reduced from critical
                    'Suspicious activity detected from IP: ' . $request->ip()
                );

                break;
            }
        }
    }

    private function addSecurityHeaders(Response $response): void
    {
        $headers = [
            // Prevent clickjacking
            'X-Frame-Options' => 'DENY',

            // Prevent MIME type sniffing
            'X-Content-Type-Options' => 'nosniff',

            // XSS Protection
            'X-XSS-Protection' => '1; mode=block',

            // Referrer Policy
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Content Security Policy
            'Content-Security-Policy' => "default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:; font-src 'self' data:; connect-src 'self'; frame-ancestors 'none';",

            // Strict Transport Security (HTTPS only)
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',

            // Permissions Policy
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
        ];

        foreach ($headers as $key => $value) {
            $response->headers->set($key, $value);
        }
    }
}
