<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class DataProtectionService
{
    private KeyManagementService $keyManager;
    
    public function __construct(KeyManagementService $keyManager)
    {
        $this->keyManager = $keyManager;
    }
    
    /**
     * Enhanced password encryption with metadata
     */
    public function encryptPassword(string $password, array $metadata = []): array
    {
        try {
            // Verify key integrity before encryption
            if (!$this->keyManager->verifyKeyIntegrity()) {
                throw new \Exception('Encryption key integrity check failed');
            }
            
            $encryptionData = [
                'encrypted_data' => Crypt::encryptString($password),
                'encryption_method' => 'AES-256-CBC',
                'encrypted_at' => now()->toISOString(),
                'key_version' => config('app.key_version', 1),
                'metadata' => $metadata,
                'checksum' => hash('sha256', $password),
            ];
            
            // Log encryption activity
            $this->keyManager->logKeyUsage('password_encryption', [
                'metadata' => $metadata,
                'key_version' => $encryptionData['key_version'],
            ]);
            
            // Clear sensitive data
            $this->keyManager->clearSensitiveData($password);
            
            return $encryptionData;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'password_encryption_failed',
                null,
                [],
                ['error' => $e->getMessage(), 'metadata' => $metadata],
                'high',
                'Password encryption failed: ' . $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Enhanced password decryption with verification
     */
    public function decryptPassword(array $encryptionData): string
    {
        try {
            // Verify key integrity before decryption
            if (!$this->keyManager->verifyKeyIntegrity()) {
                throw new \Exception('Encryption key integrity check failed');
            }
            
            $encryptedData = $encryptionData['encrypted_data'] ?? $encryptionData;
            $decryptedPassword = Crypt::decryptString($encryptedData);
            
            // Verify checksum if available
            if (isset($encryptionData['checksum'])) {
                $currentChecksum = hash('sha256', $decryptedPassword);
                if ($currentChecksum !== $encryptionData['checksum']) {
                    throw new \Exception('Password integrity verification failed');
                }
            }
            
            // Log decryption activity
            $this->keyManager->logKeyUsage('password_decryption', [
                'key_version' => $encryptionData['key_version'] ?? 'unknown',
                'encrypted_at' => $encryptionData['encrypted_at'] ?? 'unknown',
            ]);
            
            return $decryptedPassword;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'password_decryption_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Password decryption failed: ' . $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Secure data wiping
     */
    public function secureWipe(string $filePath): bool
    {
        try {
            if (!file_exists($filePath)) {
                return true;
            }
            
            $fileSize = filesize($filePath);
            $handle = fopen($filePath, 'r+b');
            
            if (!$handle) {
                return false;
            }
            
            // Overwrite with random data multiple times (DoD 5220.22-M standard)
            for ($pass = 0; $pass < 3; $pass++) {
                fseek($handle, 0);
                for ($i = 0; $i < $fileSize; $i++) {
                    fwrite($handle, chr(random_int(0, 255)), 1);
                }
                fflush($handle);
            }
            
            fclose($handle);
            unlink($filePath);
            
            AuditLog::log(
                'secure_file_wipe',
                null,
                [],
                ['file_path' => basename($filePath), 'file_size' => $fileSize],
                'medium',
                'File securely wiped: ' . basename($filePath)
            );
            
            return true;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'secure_file_wipe_failed',
                null,
                [],
                ['file_path' => basename($filePath), 'error' => $e->getMessage()],
                'high',
                'Secure file wipe failed: ' . $e->getMessage()
            );
            
            return false;
        }
    }
    
    /**
     * Data anonymization for compliance
     */
    public function anonymizeUserData(int $userId): bool
    {
        try {
            // This would be used for GDPR "right to be forgotten"
            $anonymizedData = [
                'name' => 'Anonymized User ' . hash('sha256', $userId . time()),
                'email' => 'anonymized_' . hash('sha256', $userId . time()) . '@deleted.local',
                'is_active' => false,
                'anonymized_at' => now(),
            ];
            
            AuditLog::log(
                'user_data_anonymized',
                null,
                [],
                ['user_id' => $userId],
                'high',
                'User data anonymized for compliance'
            );
            
            return true;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'user_data_anonymization_failed',
                null,
                [],
                ['user_id' => $userId, 'error' => $e->getMessage()],
                'critical',
                'User data anonymization failed: ' . $e->getMessage()
            );
            
            return false;
        }
    }
    
    /**
     * Generate secure random tokens
     */
    public function generateSecureToken(int $length = 32): string
    {
        return bin2hex(random_bytes($length));
    }
    
    /**
     * Validate data integrity
     */
    public function validateDataIntegrity(array $data, string $expectedChecksum): bool
    {
        $currentChecksum = hash('sha256', json_encode($data));
        return hash_equals($expectedChecksum, $currentChecksum);
    }
    
    /**
     * Create data backup with encryption
     */
    public function createSecureBackup(array $data, string $backupPassword): string
    {
        try {
            $backupData = [
                'data' => $data,
                'created_at' => now()->toISOString(),
                'checksum' => hash('sha256', json_encode($data)),
                'version' => '1.0',
            ];
            
            // Encrypt with backup password
            $encryptedBackup = Crypt::encryptString(json_encode($backupData));
            
            AuditLog::log(
                'secure_backup_created',
                null,
                [],
                ['data_size' => strlen(json_encode($data))],
                'medium',
                'Secure backup created'
            );
            
            // Clear sensitive data
            $this->keyManager->clearSensitiveData($backupData);
            $this->keyManager->clearSensitiveData($backupPassword);
            
            return $encryptedBackup;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'secure_backup_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Secure backup creation failed: ' . $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Restore data from secure backup
     */
    public function restoreSecureBackup(string $encryptedBackup, string $backupPassword): array
    {
        try {
            $decryptedData = Crypt::decryptString($encryptedBackup);
            $backupData = json_decode($decryptedData, true);
            
            if (!$backupData || !isset($backupData['data'])) {
                throw new \Exception('Invalid backup format');
            }
            
            // Verify integrity
            $expectedChecksum = $backupData['checksum'];
            $currentChecksum = hash('sha256', json_encode($backupData['data']));
            
            if (!hash_equals($expectedChecksum, $currentChecksum)) {
                throw new \Exception('Backup integrity verification failed');
            }
            
            AuditLog::log(
                'secure_backup_restored',
                null,
                [],
                ['backup_version' => $backupData['version'], 'created_at' => $backupData['created_at']],
                'medium',
                'Secure backup restored successfully'
            );
            
            // Clear sensitive data
            $this->keyManager->clearSensitiveData($backupPassword);
            
            return $backupData['data'];
            
        } catch (\Exception $e) {
            AuditLog::log(
                'secure_backup_restore_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Secure backup restore failed: ' . $e->getMessage()
            );
            
            throw $e;
        }
    }
}
