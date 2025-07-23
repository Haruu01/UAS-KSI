<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // Check if user is authenticated
        if (!$user) {
            AuditLog::log(
                'unauthorized_admin_access_attempt',
                null,
                [],
                ['url' => $request->fullUrl()],
                'high',
                'Unauthenticated admin access attempt'
            );

            return redirect()->route('filament.adminn.auth.login');
        }

        // Check if user is active
        if (!$user->is_active) {
            AuditLog::log(
                'inactive_user_access_attempt',
                $user,
                [],
                ['url' => $request->fullUrl()],
                'high',
                'Inactive user attempted admin access'
            );

            auth()->logout();
            return redirect()->route('filament.adminn.auth.login')
                ->with('error', 'Your account has been deactivated.');
        }

        // Check if user account is locked
        if ($user->isLocked()) {
            AuditLog::log(
                'locked_user_access_attempt',
                $user,
                [],
                ['url' => $request->fullUrl(), 'locked_until' => $user->locked_until],
                'high',
                'Locked user attempted admin access'
            );

            auth()->logout();
            return redirect()->route('filament.adminn.auth.login')
                ->with('error', 'Your account is temporarily locked due to multiple failed login attempts.');
        }

        // Check if user has admin role
        if (!$user->isAdmin()) {
            AuditLog::log(
                'unauthorized_admin_access_attempt',
                $user,
                [],
                ['url' => $request->fullUrl(), 'user_role' => $user->role],
                'high',
                'Non-admin user attempted admin access'
            );

            auth()->logout();
            return redirect()->route('filament.adminn.auth.login')
                ->with('error', 'Unauthorized. Admin access required.');
        }

        // Log successful admin access
        AuditLog::log(
            'admin_access',
            $user,
            [],
            ['url' => $request->fullUrl()],
            'medium',
            'Admin user accessed admin panel'
        );

        return $next($request);
    }
}
