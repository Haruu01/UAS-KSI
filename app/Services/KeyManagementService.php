<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;

class KeyManagementService
{
    private const KEY_ROTATION_INTERVAL = 90; // days
    private const KEY_BACKUP_PATH = 'keys/backup/';
    
    /**
     * Check if key rotation is needed
     */
    public function needsKeyRotation(): bool
    {
        $lastRotation = Cache::get('last_key_rotation', now()->subDays(self::KEY_ROTATION_INTERVAL + 1));
        return now()->diffInDays($lastRotation) >= self::KEY_ROTATION_INTERVAL;
    }
    
    /**
     * Generate new encryption key
     */
    public function generateNewKey(): string
    {
        return base64_encode(random_bytes(32)); // 256-bit key
    }
    
    /**
     * Backup current key before rotation
     */
    public function backupCurrentKey(): bool
    {
        try {
            $currentKey = config('app.key');
            $backupPath = storage_path('app/' . self::KEY_BACKUP_PATH);
            
            if (!File::exists($backupPath)) {
                File::makeDirectory($backupPath, 0700, true);
            }
            
            $backupFile = $backupPath . 'key_' . now()->format('Y-m-d_H-i-s') . '.backup';
            
            // Encrypt the key with a master key (in production, use HSM)
            $encryptedKey = Crypt::encryptString($currentKey);
            
            File::put($backupFile, $encryptedKey);
            File::chmod($backupFile, 0600);
            
            AuditLog::log(
                'encryption_key_backed_up',
                null,
                [],
                ['backup_file' => basename($backupFile)],
                'high',
                'Encryption key backed up before rotation'
            );
            
            return true;
        } catch (\Exception $e) {
            AuditLog::log(
                'encryption_key_backup_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'critical',
                'Failed to backup encryption key: ' . $e->getMessage()
            );
            
            return false;
        }
    }
    
    /**
     * Validate key strength
     */
    public function validateKeyStrength(string $key): array
    {
        $errors = [];
        $decodedKey = base64_decode($key);
        
        if (strlen($decodedKey) < 32) {
            $errors[] = 'Key must be at least 256 bits (32 bytes)';
        }
        
        if (strlen($decodedKey) > 64) {
            $errors[] = 'Key should not exceed 512 bits (64 bytes)';
        }
        
        // Check for weak patterns
        if (preg_match('/(.)\1{3,}/', $decodedKey)) {
            $errors[] = 'Key contains repeated patterns';
        }
        
        // Entropy check (simplified)
        $entropy = $this->calculateEntropy($decodedKey);
        if ($entropy < 7.0) {
            $errors[] = 'Key has insufficient entropy';
        }
        
        return $errors;
    }
    
    /**
     * Calculate entropy of a string
     */
    private function calculateEntropy(string $data): float
    {
        $frequencies = array_count_values(str_split($data));
        $length = strlen($data);
        $entropy = 0;
        
        foreach ($frequencies as $frequency) {
            $probability = $frequency / $length;
            $entropy -= $probability * log($probability, 2);
        }
        
        return $entropy;
    }
    
    /**
     * Secure memory clearing (best effort in PHP)
     */
    public function clearSensitiveData(&$data): void
    {
        if (is_string($data)) {
            // Overwrite with random data multiple times
            for ($i = 0; $i < 3; $i++) {
                $data = str_repeat(chr(random_int(0, 255)), strlen($data));
            }
            $data = null;
        } elseif (is_array($data)) {
            foreach ($data as &$value) {
                $this->clearSensitiveData($value);
            }
            $data = [];
        }
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
    }
    
    /**
     * Get key rotation status
     */
    public function getKeyRotationStatus(): array
    {
        $lastRotation = Cache::get('last_key_rotation');
        $needsRotation = $this->needsKeyRotation();
        
        return [
            'last_rotation' => $lastRotation,
            'needs_rotation' => $needsRotation,
            'days_since_rotation' => $lastRotation ? now()->diffInDays($lastRotation) : null,
            'next_rotation_due' => $lastRotation ? $lastRotation->addDays(self::KEY_ROTATION_INTERVAL) : null,
        ];
    }
    
    /**
     * Log key usage for monitoring
     */
    public function logKeyUsage(string $operation, array $context = []): void
    {
        AuditLog::log(
            'encryption_key_used',
            null,
            [],
            array_merge(['operation' => $operation], $context),
            'low',
            "Encryption key used for: {$operation}"
        );
    }
    
    /**
     * Verify key integrity
     */
    public function verifyKeyIntegrity(): bool
    {
        try {
            $testData = 'integrity_test_' . random_bytes(16);
            $encrypted = Crypt::encryptString($testData);
            $decrypted = Crypt::decryptString($encrypted);
            
            $isValid = $testData === $decrypted;
            
            if (!$isValid) {
                AuditLog::log(
                    'encryption_key_integrity_failed',
                    null,
                    [],
                    [],
                    'critical',
                    'Encryption key integrity verification failed'
                );
            }
            
            // Clear test data
            $this->clearSensitiveData($testData);
            $this->clearSensitiveData($decrypted);
            
            return $isValid;
        } catch (\Exception $e) {
            AuditLog::log(
                'encryption_key_integrity_error',
                null,
                [],
                ['error' => $e->getMessage()],
                'critical',
                'Error during key integrity verification: ' . $e->getMessage()
            );
            
            return false;
        }
    }
}
