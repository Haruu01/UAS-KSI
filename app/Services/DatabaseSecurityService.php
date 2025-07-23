<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSecurityService
{
    /**
     * Encrypt sensitive columns in existing data
     */
    public function encryptSensitiveColumns(): bool
    {
        try {
            DB::beginTransaction();
            
            // Get all password entries that might not be encrypted
            $entries = DB::table('password_entries')
                ->whereNull('encrypted_password')
                ->orWhere('encrypted_password', '')
                ->get();
            
            foreach ($entries as $entry) {
                if (!empty($entry->password)) {
                    // Encrypt the password if it's still in plain text
                    DB::table('password_entries')
                        ->where('id', $entry->id)
                        ->update([
                            'encrypted_password' => encrypt($entry->password),
                            'password' => null, // Clear plain text
                        ]);
                }
            }
            
            DB::commit();
            
            AuditLog::log(
                'database_encryption_applied',
                null,
                [],
                ['entries_encrypted' => $entries->count()],
                'high',
                'Database sensitive columns encrypted'
            );
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            AuditLog::log(
                'database_encryption_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'critical',
                'Database encryption failed: ' . $e->getMessage()
            );
            
            return false;
        }
    }
    
    /**
     * Add database constraints for security
     */
    public function addSecurityConstraints(): bool
    {
        try {
            // Add check constraints for data validation
            if (Schema::hasTable('users')) {
                // Ensure email format
                DB::statement('ALTER TABLE users ADD CONSTRAINT chk_email_format CHECK (email LIKE "%@%.%")');
                
                // Ensure role values
                DB::statement('ALTER TABLE users ADD CONSTRAINT chk_role_values CHECK (role IN ("user", "admin"))');
            }
            
            if (Schema::hasTable('password_entries')) {
                // Ensure password strength is valid
                DB::statement('ALTER TABLE password_entries ADD CONSTRAINT chk_password_strength CHECK (password_strength BETWEEN 1 AND 5)');
                
                // Ensure encrypted_password is not empty
                DB::statement('ALTER TABLE password_entries ADD CONSTRAINT chk_encrypted_password CHECK (LENGTH(encrypted_password) > 0)');
            }
            
            AuditLog::log(
                'database_constraints_added',
                null,
                [],
                [],
                'medium',
                'Database security constraints added'
            );
            
            return true;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'database_constraints_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Failed to add database constraints: ' . $e->getMessage()
            );
            
            return false;
        }
    }
    
    /**
     * Create database backup with encryption
     */
    public function createEncryptedBackup(string $backupPassword): string
    {
        try {
            $backupData = [];
            
            // Export all tables
            $tables = ['users', 'categories', 'password_entries', 'shared_passwords', 'audit_logs'];
            
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    $backupData[$table] = DB::table($table)->get()->toArray();
                }
            }
            
            // Add metadata
            $backupData['_metadata'] = [
                'created_at' => now()->toISOString(),
                'version' => '1.0',
                'tables' => array_keys($backupData),
                'checksum' => hash('sha256', json_encode($backupData)),
            ];
            
            // Encrypt backup
            $encryptedBackup = encrypt(json_encode($backupData));
            
            // Save to file
            $filename = 'database_backup_' . now()->format('Y-m-d_H-i-s') . '.enc';
            $filepath = storage_path('app/backups/' . $filename);
            
            if (!file_exists(dirname($filepath))) {
                mkdir(dirname($filepath), 0700, true);
            }
            
            file_put_contents($filepath, $encryptedBackup);
            chmod($filepath, 0600);
            
            AuditLog::log(
                'database_backup_created',
                null,
                [],
                ['filename' => $filename, 'tables' => count($tables)],
                'medium',
                'Encrypted database backup created'
            );
            
            return $filepath;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'database_backup_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'critical',
                'Database backup failed: ' . $e->getMessage()
            );
            
            throw $e;
        }
    }
    
    /**
     * Verify database integrity
     */
    public function verifyDatabaseIntegrity(): array
    {
        $issues = [];
        
        try {
            // Check for unencrypted passwords
            $unencryptedCount = DB::table('password_entries')
                ->whereNull('encrypted_password')
                ->orWhere('encrypted_password', '')
                ->count();
            
            if ($unencryptedCount > 0) {
                $issues[] = "Found {$unencryptedCount} unencrypted password entries";
            }
            
            // Check for weak passwords (if any are stored in plain text)
            $weakPasswords = DB::table('password_entries')
                ->where('password_strength', '<=', 2)
                ->count();
            
            if ($weakPasswords > 0) {
                $issues[] = "Found {$weakPasswords} weak passwords";
            }
            
            // Check for inactive users with data
            $inactiveUsersWithData = DB::table('users')
                ->join('password_entries', 'users.id', '=', 'password_entries.user_id')
                ->where('users.is_active', false)
                ->distinct('users.id')
                ->count();
            
            if ($inactiveUsersWithData > 0) {
                $issues[] = "Found {$inactiveUsersWithData} inactive users with password data";
            }
            
            // Check for orphaned records
            $orphanedPasswords = DB::table('password_entries')
                ->leftJoin('users', 'password_entries.user_id', '=', 'users.id')
                ->whereNull('users.id')
                ->count();
            
            if ($orphanedPasswords > 0) {
                $issues[] = "Found {$orphanedPasswords} orphaned password entries";
            }
            
            AuditLog::log(
                'database_integrity_check',
                null,
                [],
                ['issues_found' => count($issues), 'issues' => $issues],
                count($issues) > 0 ? 'medium' : 'low',
                'Database integrity check completed'
            );
            
        } catch (\Exception $e) {
            $issues[] = "Integrity check failed: " . $e->getMessage();
            
            AuditLog::log(
                'database_integrity_check_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'high',
                'Database integrity check failed'
            );
        }
        
        return $issues;
    }
    
    /**
     * Clean up old audit logs
     */
    public function cleanupOldAuditLogs(int $daysToKeep = 90): int
    {
        try {
            $cutoffDate = now()->subDays($daysToKeep);
            
            $deletedCount = DB::table('audit_logs')
                ->where('created_at', '<', $cutoffDate)
                ->where('severity', 'low') // Only delete low severity logs
                ->delete();
            
            AuditLog::log(
                'audit_logs_cleaned',
                null,
                [],
                ['deleted_count' => $deletedCount, 'cutoff_date' => $cutoffDate->toDateString()],
                'low',
                "Cleaned up {$deletedCount} old audit logs"
            );
            
            return $deletedCount;
            
        } catch (\Exception $e) {
            AuditLog::log(
                'audit_logs_cleanup_failed',
                null,
                [],
                ['error' => $e->getMessage()],
                'medium',
                'Audit logs cleanup failed: ' . $e->getMessage()
            );
            
            return 0;
        }
    }
    
    /**
     * Get database security status
     */
    public function getSecurityStatus(): array
    {
        return [
            'encrypted_passwords' => DB::table('password_entries')
                ->whereNotNull('encrypted_password')
                ->where('encrypted_password', '!=', '')
                ->count(),
            'total_passwords' => DB::table('password_entries')->count(),
            'active_users' => DB::table('users')->where('is_active', true)->count(),
            'total_users' => DB::table('users')->count(),
            'audit_logs_count' => DB::table('audit_logs')->count(),
            'integrity_issues' => $this->verifyDatabaseIntegrity(),
        ];
    }
}
