<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CustomLoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $email = $request->email;
        $password = $request->password;
        $ip = $request->ip();

        // Rate limiting
        $key = 'login.' . $ip;
        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);

            AuditLog::log(
                'login_rate_limit_exceeded',
                null,
                [],
                ['email' => $email, 'ip' => $ip, 'retry_after' => $seconds],
                'high',
                "Login rate limit exceeded for IP: {$ip}"
            );

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        // Find user
        $user = User::where('email', $email)->first();

        if (!$user) {
            RateLimiter::hit($key, 300); // 5 minutes

            AuditLog::log(
                'login_failed_user_not_found',
                null,
                [],
                ['email' => $email, 'ip' => $ip],
                'medium',
                "Login attempt with non-existent email: {$email}"
            );

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        // Check if user is active
        if (!$user->is_active) {
            AuditLog::log(
                'login_failed_inactive_user',
                $user,
                [],
                ['ip' => $ip],
                'high',
                "Login attempt by inactive user: {$email}"
            );

            throw ValidationException::withMessages([
                'email' => 'Your account has been deactivated.',
            ]);
        }

        // Check if user is locked
        if ($user->isLocked()) {
            AuditLog::log(
                'login_failed_locked_user',
                $user,
                [],
                ['ip' => $ip, 'locked_until' => $user->locked_until],
                'high',
                "Login attempt by locked user: {$email}"
            );

            throw ValidationException::withMessages([
                'email' => 'Your account is temporarily locked due to multiple failed login attempts.',
            ]);
        }

        // Verify password
        if (!Hash::check($password, $user->password)) {
            RateLimiter::hit($key, 300); // 5 minutes

            $user->incrementFailedLogins();

            AuditLog::log(
                'login_failed_wrong_password',
                $user,
                [],
                ['ip' => $ip, 'failed_attempts' => $user->failed_login_attempts],
                'high',
                "Login failed with wrong password for user: {$email}"
            );

            throw ValidationException::withMessages([
                'email' => 'The provided credentials are incorrect.',
            ]);
        }

        // Successful login
        RateLimiter::clear($key);
        $user->resetFailedLogins();

        Auth::login($user, $request->boolean('remember'));

        AuditLog::log(
            'login_successful',
            $user,
            [],
            ['ip' => $ip, 'user_agent' => $request->userAgent()],
            'low',
            "Successful login for user: {$email}"
        );

        $request->session()->regenerate();

        return redirect()->intended('/adminn');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            AuditLog::log(
                'logout',
                $user,
                [],
                ['ip' => $request->ip()],
                'low',
                "User logged out: {$user->email}"
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/adminn/login');
    }
}
