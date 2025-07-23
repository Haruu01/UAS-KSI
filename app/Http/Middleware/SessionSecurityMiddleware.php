<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class SessionSecurityMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            // Validate session security
            $this->validateSessionSecurity($request);
            
            // Check for session hijacking
            $this->detectSessionHijacking($request);
            
            // Update session activity
            $this->updateSessionActivity($request);
            
            // Check for concurrent sessions
            $this->manageConcurrentSessions($request);
        }
        
        return $next($request);
    }
    
    private function validateSessionSecurity(Request $request): void
    {
        $user = Auth::user();
        $sessionId = session()->getId();
        
        // Check if session is valid
        $sessionKey = "session_security:{$user->id}:{$sessionId}";
        $sessionData = Cache::get($sessionKey);
        
        if (!$sessionData) {
            // First time seeing this session, create security data
            $sessionData = [
                'created_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'last_activity' => now(),
                'activity_count' => 1,
            ];
            
            Cache::put($sessionKey, $sessionData, 86400); // 24 hours
            
            AuditLog::log(
                'new_session_created',
                null,
                [],
                [
                    'user_id' => $user->id,
                    'session_id' => substr($sessionId, 0, 8) . '...',
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent()
                ],
                'medium',
                "New session created for user {$user->email}"
            );
        }
    }
    
    private function detectSessionHijacking(Request $request): void
    {
        $user = Auth::user();
        $sessionId = session()->getId();
        $sessionKey = "session_security:{$user->id}:{$sessionId}";
        $sessionData = Cache::get($sessionKey);
        
        if (!$sessionData) {
            return;
        }
        
        $currentIp = $request->ip();
        $currentUserAgent = $request->userAgent();
        $originalIp = $sessionData['ip_address'];
        $originalUserAgent = $sessionData['user_agent'];
        
        $suspicious = false;
        $reasons = [];
        
        // Check for IP address changes
        if ($currentIp !== $originalIp) {
            // Allow some flexibility for mobile users and corporate networks
            if (!$this->isAllowedIpChange($originalIp, $currentIp)) {
                $suspicious = true;
                $reasons[] = 'IP address changed';
            }
        }
        
        // Check for user agent changes
        if ($currentUserAgent !== $originalUserAgent) {
            // Minor user agent changes are acceptable (browser updates)
            if (!$this->isAllowedUserAgentChange($originalUserAgent, $currentUserAgent)) {
                $suspicious = true;
                $reasons[] = 'User agent changed significantly';
            }
        }
        
        // Check for unusual activity patterns
        if ($this->hasUnusualActivityPattern($sessionData)) {
            $suspicious = true;
            $reasons[] = 'Unusual activity pattern detected';
        }
        
        if ($suspicious) {
            $this->handleSuspiciousSession($request, $user, $reasons);
        }
    }
    
    private function isAllowedIpChange(string $originalIp, string $currentIp): bool
    {
        // Allow changes within the same subnet (common in corporate networks)
        $originalParts = explode('.', $originalIp);
        $currentParts = explode('.', $currentIp);
        
        // Same /24 subnet
        if (count($originalParts) === 4 && count($currentParts) === 4) {
            return $originalParts[0] === $currentParts[0] &&
                   $originalParts[1] === $currentParts[1] &&
                   $originalParts[2] === $currentParts[2];
        }
        
        return false;
    }
    
    private function isAllowedUserAgentChange(?string $original, ?string $current): bool
    {
        if (!$original || !$current) {
            return false;
        }
        
        // Extract browser and OS information
        $originalBrowser = $this->extractBrowserInfo($original);
        $currentBrowser = $this->extractBrowserInfo($current);
        
        // Allow minor version changes but not major browser/OS changes
        return $originalBrowser['browser'] === $currentBrowser['browser'] &&
               $originalBrowser['os'] === $currentBrowser['os'];
    }
    
    private function extractBrowserInfo(string $userAgent): array
    {
        $browser = 'unknown';
        $os = 'unknown';
        
        // Detect browser
        if (str_contains($userAgent, 'Chrome')) $browser = 'Chrome';
        elseif (str_contains($userAgent, 'Firefox')) $browser = 'Firefox';
        elseif (str_contains($userAgent, 'Safari')) $browser = 'Safari';
        elseif (str_contains($userAgent, 'Edge')) $browser = 'Edge';
        
        // Detect OS
        if (str_contains($userAgent, 'Windows')) $os = 'Windows';
        elseif (str_contains($userAgent, 'Mac')) $os = 'Mac';
        elseif (str_contains($userAgent, 'Linux')) $os = 'Linux';
        elseif (str_contains($userAgent, 'Android')) $os = 'Android';
        elseif (str_contains($userAgent, 'iOS')) $os = 'iOS';
        
        return ['browser' => $browser, 'os' => $os];
    }
    
    private function hasUnusualActivityPattern(array $sessionData): bool
    {
        $activityCount = $sessionData['activity_count'] ?? 0;
        $sessionAge = now()->diffInMinutes($sessionData['created_at']);
        
        // Too many requests in short time
        if ($sessionAge < 5 && $activityCount > 100) {
            return true;
        }
        
        // Extremely high activity rate
        if ($sessionAge > 0 && ($activityCount / $sessionAge) > 10) {
            return true;
        }
        
        return false;
    }
    
    private function handleSuspiciousSession(Request $request, $user, array $reasons): void
    {
        AuditLog::log(
            'suspicious_session_detected',
            null,
            [],
            [
                'user_id' => $user->id,
                'session_id' => substr(session()->getId(), 0, 8) . '...',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'reasons' => $reasons
            ],
            'critical',
            "Suspicious session activity detected for user {$user->email}: " . implode(', ', $reasons)
        );
        
        // Force re-authentication for suspicious sessions
        Auth::logout();
        session()->invalidate();
        session()->regenerateToken();
        
        abort(401, 'Session security violation detected. Please log in again.');
    }
    
    private function updateSessionActivity(Request $request): void
    {
        $user = Auth::user();
        $sessionId = session()->getId();
        $sessionKey = "session_security:{$user->id}:{$sessionId}";
        $sessionData = Cache::get($sessionKey, []);
        
        $sessionData['last_activity'] = now();
        $sessionData['activity_count'] = ($sessionData['activity_count'] ?? 0) + 1;
        
        Cache::put($sessionKey, $sessionData, 86400);
    }
    
    private function manageConcurrentSessions(Request $request): void
    {
        $user = Auth::user();
        $currentSessionId = session()->getId();
        
        // Track active sessions for this user
        $userSessionsKey = "user_sessions:{$user->id}";
        $activeSessions = Cache::get($userSessionsKey, []);
        
        // Add current session
        $activeSessions[$currentSessionId] = [
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'last_activity' => now(),
        ];
        
        // Remove expired sessions (inactive for more than 2 hours)
        $activeSessions = array_filter($activeSessions, function ($session) {
            return now()->diffInHours($session['last_activity']) < 2;
        });
        
        // Limit concurrent sessions (max 3)
        if (count($activeSessions) > 3) {
            // Remove oldest sessions
            uasort($activeSessions, function ($a, $b) {
                return $a['last_activity'] <=> $b['last_activity'];
            });
            
            $activeSessions = array_slice($activeSessions, -3, 3, true);
            
            AuditLog::log(
                'concurrent_session_limit_enforced',
                null,
                [],
                [
                    'user_id' => $user->id,
                    'active_sessions' => count($activeSessions)
                ],
                'medium',
                "Concurrent session limit enforced for user {$user->email}"
            );
        }
        
        Cache::put($userSessionsKey, $activeSessions, 86400);
    }
}
