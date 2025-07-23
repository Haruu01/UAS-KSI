<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AdvancedRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $userAgent = $request->userAgent();
        
        // Different rate limits for different endpoints
        $this->applyEndpointSpecificLimits($request, $ip);
        
        // Detect and block suspicious patterns
        $this->detectSuspiciousActivity($request, $ip, $userAgent);
        
        // Apply progressive penalties for repeat offenders
        $this->applyProgressivePenalties($request, $ip);
        
        return $next($request);
    }
    
    private function applyEndpointSpecificLimits(Request $request, string $ip): void
    {
        $endpoint = $request->getPathInfo();
        
        // Login endpoint - strict limits
        if (str_contains($endpoint, 'login')) {
            $key = "login_attempts:{$ip}";
            $maxAttempts = 5;
            $decayMinutes = 15;
            
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $this->logSecurityEvent('rate_limit_exceeded', $ip, [
                    'endpoint' => 'login',
                    'attempts' => RateLimiter::attempts($key),
                    'max_attempts' => $maxAttempts
                ]);
                
                abort(429, 'Too many login attempts. Please try again in 15 minutes.');
            }
            
            RateLimiter::hit($key, $decayMinutes * 60);
        }
        
        // API endpoints - moderate limits
        if (str_contains($endpoint, 'livewire')) {
            $key = "api_requests:{$ip}";
            $maxAttempts = 200;
            $decayMinutes = 1;
            
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $this->logSecurityEvent('api_rate_limit_exceeded', $ip, [
                    'endpoint' => 'livewire',
                    'attempts' => RateLimiter::attempts($key)
                ]);
                
                abort(429, 'API rate limit exceeded. Please slow down.');
            }
            
            RateLimiter::hit($key, $decayMinutes * 60);
        }
        
        // Password operations - special limits
        if (str_contains($endpoint, 'password-entries')) {
            $key = "password_operations:{$ip}";
            $maxAttempts = 50;
            $decayMinutes = 5;
            
            if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
                $this->logSecurityEvent('password_operations_rate_limit', $ip, [
                    'endpoint' => 'password_operations',
                    'attempts' => RateLimiter::attempts($key)
                ]);
                
                abort(429, 'Too many password operations. Please wait 5 minutes.');
            }
            
            RateLimiter::hit($key, $decayMinutes * 60);
        }
    }
    
    private function detectSuspiciousActivity(Request $request, string $ip, ?string $userAgent): void
    {
        // Detect bot-like behavior
        if ($this->isSuspiciousUserAgent($userAgent)) {
            $this->logSecurityEvent('suspicious_user_agent', $ip, [
                'user_agent' => $userAgent,
                'endpoint' => $request->getPathInfo()
            ]);
            
            // Apply stricter rate limits for bots
            $key = "bot_requests:{$ip}";
            if (RateLimiter::tooManyAttempts($key, 10)) {
                abort(429, 'Automated requests detected. Access temporarily restricted.');
            }
            RateLimiter::hit($key, 3600); // 1 hour
        }
        
        // Detect rapid sequential requests
        $this->detectRapidRequests($ip);
        
        // Detect unusual request patterns
        $this->detectUnusualPatterns($request, $ip);
    }
    
    private function isSuspiciousUserAgent(?string $userAgent): bool
    {
        if (!$userAgent) return true;
        
        $suspiciousPatterns = [
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget',
            'python', 'java', 'go-http', 'okhttp', 'apache-httpclient'
        ];
        
        $userAgentLower = strtolower($userAgent);
        
        foreach ($suspiciousPatterns as $pattern) {
            if (str_contains($userAgentLower, $pattern)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function detectRapidRequests(string $ip): void
    {
        $key = "rapid_requests:{$ip}";
        $requests = Cache::get($key, []);
        $now = time();
        
        // Keep only requests from last 10 seconds
        $requests = array_filter($requests, fn($time) => $now - $time < 10);
        $requests[] = $now;
        
        Cache::put($key, $requests, 60);
        
        // If more than 20 requests in 10 seconds, it's suspicious
        if (count($requests) > 20) {
            $this->logSecurityEvent('rapid_requests_detected', $ip, [
                'requests_count' => count($requests),
                'time_window' => '10_seconds'
            ]);
            
            // Temporary block
            $blockKey = "blocked_ip:{$ip}";
            Cache::put($blockKey, true, 300); // 5 minutes
            
            abort(429, 'Rapid requests detected. IP temporarily blocked.');
        }
    }
    
    private function detectUnusualPatterns(Request $request, string $ip): void
    {
        $endpoint = $request->getPathInfo();
        
        // Track endpoint access patterns
        $key = "endpoint_pattern:{$ip}";
        $patterns = Cache::get($key, []);
        $patterns[] = [
            'endpoint' => $endpoint,
            'time' => time(),
            'method' => $request->method()
        ];
        
        // Keep only last 50 requests
        $patterns = array_slice($patterns, -50);
        Cache::put($key, $patterns, 3600);
        
        // Detect if accessing too many different endpoints rapidly
        $recentEndpoints = array_unique(
            array_column(
                array_filter($patterns, fn($p) => time() - $p['time'] < 60),
                'endpoint'
            )
        );
        
        if (count($recentEndpoints) > 10) {
            $this->logSecurityEvent('unusual_access_pattern', $ip, [
                'unique_endpoints' => count($recentEndpoints),
                'time_window' => '60_seconds'
            ]);
        }
    }
    
    private function applyProgressivePenalties(Request $request, string $ip): void
    {
        // Check if IP is in penalty box
        $penaltyKey = "penalty_box:{$ip}";
        $penalties = Cache::get($penaltyKey, 0);
        
        if ($penalties > 0) {
            // Progressive delay based on penalty count
            $delay = min($penalties * 2, 30); // Max 30 seconds
            sleep($delay);
            
            $this->logSecurityEvent('progressive_penalty_applied', $ip, [
                'penalty_count' => $penalties,
                'delay_seconds' => $delay
            ]);
        }
    }
    
    public static function addPenalty(string $ip): void
    {
        $penaltyKey = "penalty_box:{$ip}";
        $penalties = Cache::get($penaltyKey, 0);
        Cache::put($penaltyKey, $penalties + 1, 3600); // 1 hour
    }
    
    private function logSecurityEvent(string $action, string $ip, array $context): void
    {
        AuditLog::log(
            $action,
            null,
            [],
            array_merge($context, ['ip_address' => $ip]),
            'high',
            "Advanced rate limiting: {$action} from IP {$ip}"
        );
    }
}
