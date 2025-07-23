<?php

namespace App\Services;

use App\Models\AuditLog;

class PasswordSecurityService
{
    /**
     * Generate a secure password
     */
    public function generatePassword(array $options = []): string
    {
        $length = $options['length'] ?? 16;
        $includeUppercase = $options['uppercase'] ?? true;
        $includeLowercase = $options['lowercase'] ?? true;
        $includeNumbers = $options['numbers'] ?? true;
        $includeSymbols = $options['symbols'] ?? true;
        $excludeAmbiguous = $options['exclude_ambiguous'] ?? true;

        $characters = '';
        
        if ($includeLowercase) {
            $characters .= $excludeAmbiguous ? 'abcdefghjkmnpqrstuvwxyz' : 'abcdefghijklmnopqrstuvwxyz';
        }
        
        if ($includeUppercase) {
            $characters .= $excludeAmbiguous ? 'ABCDEFGHJKMNPQRSTUVWXYZ' : 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        }
        
        if ($includeNumbers) {
            $characters .= $excludeAmbiguous ? '23456789' : '0123456789';
        }
        
        if ($includeSymbols) {
            $characters .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }

        if (empty($characters)) {
            $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        }

        $password = '';
        $charactersLength = strlen($characters);
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $characters[random_int(0, $charactersLength - 1)];
        }

        // Ensure password meets minimum requirements
        if ($includeUppercase && !preg_match('/[A-Z]/', $password)) {
            $password[0] = chr(random_int(65, 90)); // A-Z
        }
        
        if ($includeLowercase && !preg_match('/[a-z]/', $password)) {
            $password[1] = chr(random_int(97, 122)); // a-z
        }
        
        if ($includeNumbers && !preg_match('/[0-9]/', $password)) {
            $password[2] = chr(random_int(48, 57)); // 0-9
        }
        
        if ($includeSymbols && !preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) {
            $password[3] = '!';
        }

        return str_shuffle($password);
    }

    /**
     * Calculate password strength (1-5 scale)
     */
    public function calculateStrength(string $password): int
    {
        $score = 0;
        $length = strlen($password);

        // Length scoring
        if ($length >= 8) $score += 1;
        if ($length >= 12) $score += 1;
        if ($length >= 16) $score += 1;

        // Character variety scoring
        if (preg_match('/[a-z]/', $password)) $score += 1; // lowercase
        if (preg_match('/[A-Z]/', $password)) $score += 1; // uppercase
        if (preg_match('/[0-9]/', $password)) $score += 1; // numbers
        if (preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) $score += 1; // symbols

        // Penalty for common patterns
        if (preg_match('/(.)\1{2,}/', $password)) $score -= 1; // repeated characters
        if (preg_match('/123|abc|qwe|asd|zxc/i', $password)) $score -= 1; // common sequences

        // Penalty for dictionary words (simplified check)
        $commonWords = ['password', 'admin', 'user', 'login', 'welcome', '123456', 'qwerty'];
        foreach ($commonWords as $word) {
            if (stripos($password, $word) !== false) {
                $score -= 2;
                break;
            }
        }

        return max(1, min(5, $score));
    }

    /**
     * Get password strength description
     */
    public function getStrengthDescription(int $strength): string
    {
        return match ($strength) {
            1 => 'Very Weak',
            2 => 'Weak',
            3 => 'Fair',
            4 => 'Strong',
            5 => 'Very Strong',
            default => 'Unknown'
        };
    }

    /**
     * Get password strength color
     */
    public function getStrengthColor(int $strength): string
    {
        return match ($strength) {
            1 => '#EF4444', // red
            2 => '#F97316', // orange
            3 => '#EAB308', // yellow
            4 => '#22C55E', // green
            5 => '#16A34A', // dark green
            default => '#6B7280' // gray
        };
    }

    /**
     * Check if password has been compromised (simplified check)
     */
    public function isCompromised(string $password): bool
    {
        // In a real implementation, you would check against HaveIBeenPwned API
        // For now, we'll just check against common passwords
        $commonPasswords = [
            '123456', 'password', '123456789', '12345678', '12345',
            '111111', '1234567', 'sunshine', 'qwerty', 'iloveyou',
            'princess', 'admin', 'welcome', '666666', 'abc123',
            'football', '123123', 'monkey', '654321', '!@#$%^&*',
            'charlie', 'aa123456', 'donald', 'password1', 'qwerty123'
        ];

        return in_array(strtolower($password), $commonPasswords);
    }

    /**
     * Validate password against security policy
     */
    public function validatePassword(string $password): array
    {
        $errors = [];
        $length = strlen($password);

        // Minimum length
        if ($length < 8) {
            $errors[] = 'Password must be at least 8 characters long';
        }

        // Maximum length
        if ($length > 128) {
            $errors[] = 'Password must not exceed 128 characters';
        }

        // Character requirements
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }

        if (!preg_match('/[!@#$%^&*()_+\-=\[\]{}|;:,.<>?]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }

        // Check for compromised password
        if ($this->isCompromised($password)) {
            $errors[] = 'This password has been found in data breaches and should not be used';
        }

        // Check strength
        $strength = $this->calculateStrength($password);
        if ($strength < 3) {
            $errors[] = 'Password is too weak. Please choose a stronger password';
        }

        return $errors;
    }

    /**
     * Generate password suggestions
     */
    public function generateSuggestions(int $count = 3): array
    {
        $suggestions = [];
        
        for ($i = 0; $i < $count; $i++) {
            $options = [
                'length' => random_int(12, 20),
                'uppercase' => true,
                'lowercase' => true,
                'numbers' => true,
                'symbols' => true,
                'exclude_ambiguous' => true,
            ];
            
            $password = $this->generatePassword($options);
            $strength = $this->calculateStrength($password);
            
            $suggestions[] = [
                'password' => $password,
                'strength' => $strength,
                'description' => $this->getStrengthDescription($strength),
                'color' => $this->getStrengthColor($strength),
            ];
        }

        return $suggestions;
    }

    /**
     * Log password-related security events
     */
    public function logSecurityEvent(string $event, $user = null, array $context = []): void
    {
        $severity = match ($event) {
            'password_generated' => 'low',
            'weak_password_detected' => 'medium',
            'compromised_password_detected' => 'high',
            'password_strength_checked' => 'low',
            default => 'medium'
        };

        AuditLog::log($event, $user, [], $context, $severity);
    }
}
