<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseHardeningService
{
    /**
     * Apply comprehensive database security hardening
     */
    public function hardenDatabase(): array
    {
        $results = [];
        
        // Apply database constraints
        $results['constraints'] = $this->applySecurityConstraints();
        
        // Create database triggers for audit
        $results['triggers'] = $this->createAuditTriggers();
        
        // Implement row-level security
        $results['row_security'] = $this->implementRowLevelSecurity();
        
        // Create security views
        $results['views'] = $this->createSecurityViews();
        
        // Apply database permissions
        $results['permissions'] = $this->applyDatabasePermissions();
        
        return $results;
    }
    
    private function applySecurityConstraints(): array
    {
        $results = [];
        
        try {
            // Users table constraints
            if (Schema::hasTable('users')) {
                // Email format validation
                DB::statement('
                    CREATE TRIGGER IF NOT EXISTS validate_user_email 
                    BEFORE INSERT ON users 
                    FOR EACH ROW 
                    WHEN NEW.email NOT LIKE "%@%.%" 
                    BEGIN 
                        SELECT RAISE(ABORT, "Invalid email format"); 
                    END
                ');
                
                // Role validation
                DB::statement('
                    CREATE TRIGGER IF NOT EXISTS validate_user_role 
                    BEFORE INSERT ON users 
                    FOR EACH ROW 
                    WHEN NEW.role NOT IN ("user", "admin") 
                    BEGIN 
                        SELECT RAISE(ABORT, "Invalid role"); 
                    END
                ');
                
                $results['users_constraints'] = 'Applied';
            }
            
            // Password entries constraints
            if (Schema::hasTable('password_entries')) {
                // Ensure encrypted password is not empty
                DB::statement('
                    CREATE TRIGGER IF NOT EXISTS validate_encrypted_password 
                    BEFORE INSERT ON password_entries 
                    FOR EACH ROW 
                    WHEN LENGTH(NEW.encrypted_password) < 50 
                    BEGIN 
                        SELECT RAISE(ABORT, "Encrypted password too short"); 
                    END
                ');
                
                // Password strength validation
                DB::statement('
                    CREATE TRIGGER IF NOT EXISTS validate_password_strength 
                    BEFORE INSERT ON password_entries 
                    FOR EACH ROW 
                    WHEN NEW.password_strength NOT BETWEEN 1 AND 5 
                    BEGIN 
                        SELECT RAISE(ABORT, "Invalid password strength"); 
                    END
                ');
                
                $results['password_entries_constraints'] = 'Applied';
            }
            
            // Audit logs constraints
            if (Schema::hasTable('audit_logs')) {
                // Severity validation
                DB::statement('
                    CREATE TRIGGER IF NOT EXISTS validate_audit_severity 
                    BEFORE INSERT ON audit_logs 
                    FOR EACH ROW 
                    WHEN NEW.severity NOT IN ("low", "medium", "high", "critical") 
                    BEGIN 
                        SELECT RAISE(ABORT, "Invalid audit log severity"); 
                    END
                ');
                
                $results['audit_logs_constraints'] = 'Applied';
            }
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    private function createAuditTriggers(): array
    {
        $results = [];
        
        try {
            // Create audit trigger for users table
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS audit_users_changes 
                AFTER UPDATE ON users 
                FOR EACH ROW 
                BEGIN 
                    INSERT INTO audit_logs (
                        user_id, action, resource_type, resource_id, 
                        old_values, new_values, ip_address, 
                        severity, description, created_at, updated_at
                    ) VALUES (
                        NEW.id, "user_updated", "User", NEW.id,
                        json_object(
                            "name", OLD.name,
                            "email", OLD.email,
                            "role", OLD.role,
                            "is_active", OLD.is_active
                        ),
                        json_object(
                            "name", NEW.name,
                            "email", NEW.email,
                            "role", NEW.role,
                            "is_active", NEW.is_active
                        ),
                        "system",
                        "medium",
                        "User profile updated via database trigger",
                        datetime("now"),
                        datetime("now")
                    );
                END
            ');
            
            // Create audit trigger for password entries
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS audit_password_entries_changes 
                AFTER UPDATE ON password_entries 
                FOR EACH ROW 
                BEGIN 
                    INSERT INTO audit_logs (
                        user_id, action, resource_type, resource_id, 
                        old_values, new_values, ip_address, 
                        severity, description, created_at, updated_at
                    ) VALUES (
                        NEW.user_id, "password_entry_updated", "PasswordEntry", NEW.id,
                        json_object(
                            "title", OLD.title,
                            "username", OLD.username,
                            "url", OLD.url
                        ),
                        json_object(
                            "title", NEW.title,
                            "username", NEW.username,
                            "url", NEW.url
                        ),
                        "system",
                        "high",
                        "Password entry updated via database trigger",
                        datetime("now"),
                        datetime("now")
                    );
                END
            ');
            
            $results['audit_triggers'] = 'Created';
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    private function implementRowLevelSecurity(): array
    {
        $results = [];
        
        try {
            // Create security policy views that automatically filter by user
            DB::statement('
                CREATE VIEW IF NOT EXISTS user_password_entries AS
                SELECT * FROM password_entries 
                WHERE user_id = (
                    SELECT id FROM users 
                    WHERE email = CURRENT_USER 
                    LIMIT 1
                )
            ');
            
            DB::statement('
                CREATE VIEW IF NOT EXISTS user_categories AS
                SELECT * FROM categories 
                WHERE user_id = (
                    SELECT id FROM users 
                    WHERE email = CURRENT_USER 
                    LIMIT 1
                )
            ');
            
            $results['row_level_security'] = 'Implemented';
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    private function createSecurityViews(): array
    {
        $results = [];
        
        try {
            // Create view for security monitoring
            DB::statement('
                CREATE VIEW IF NOT EXISTS security_dashboard AS
                SELECT 
                    COUNT(DISTINCT u.id) as total_users,
                    COUNT(DISTINCT CASE WHEN u.is_active = 1 THEN u.id END) as active_users,
                    COUNT(DISTINCT pe.id) as total_passwords,
                    COUNT(DISTINCT CASE WHEN pe.password_strength <= 2 THEN pe.id END) as weak_passwords,
                    COUNT(DISTINCT CASE WHEN al.severity = "critical" AND al.created_at >= datetime("now", "-7 days") THEN al.id END) as critical_events_week
                FROM users u
                LEFT JOIN password_entries pe ON u.id = pe.user_id
                LEFT JOIN audit_logs al ON u.id = al.user_id
            ');
            
            // Create view for audit summary
            DB::statement('
                CREATE VIEW IF NOT EXISTS audit_summary AS
                SELECT 
                    DATE(created_at) as audit_date,
                    action,
                    severity,
                    COUNT(*) as event_count,
                    COUNT(DISTINCT user_id) as affected_users
                FROM audit_logs
                WHERE created_at >= datetime("now", "-30 days")
                GROUP BY DATE(created_at), action, severity
                ORDER BY audit_date DESC, event_count DESC
            ');
            
            $results['security_views'] = 'Created';
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    private function applyDatabasePermissions(): array
    {
        $results = [];
        
        try {
            // Note: SQLite doesn't support user-based permissions like PostgreSQL/MySQL
            // But we can create stored procedures for controlled access
            
            // Create function to safely get user passwords
            DB::statement('
                CREATE TRIGGER IF NOT EXISTS prevent_direct_password_access
                BEFORE SELECT ON password_entries
                FOR EACH ROW
                WHEN NEW.user_id != (
                    SELECT id FROM users 
                    WHERE email = CURRENT_USER 
                    LIMIT 1
                )
                BEGIN
                    SELECT RAISE(ABORT, "Access denied: Cannot access other users passwords");
                END
            ');
            
            $results['database_permissions'] = 'Applied (SQLite limitations noted)';
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
    
    /**
     * Verify database security configuration
     */
    public function verifyDatabaseSecurity(): array
    {
        $checks = [];
        
        // Check if triggers exist
        $triggers = DB::select("SELECT name FROM sqlite_master WHERE type='trigger'");
        $checks['triggers_count'] = count($triggers);
        $checks['triggers'] = array_column($triggers, 'name');
        
        // Check if views exist
        $views = DB::select("SELECT name FROM sqlite_master WHERE type='view'");
        $checks['views_count'] = count($views);
        $checks['views'] = array_column($views, 'name');
        
        // Check data integrity
        $checks['data_integrity'] = $this->checkDataIntegrity();
        
        // Check encryption status
        $checks['encryption_status'] = $this->checkEncryptionStatus();
        
        return $checks;
    }
    
    private function checkDataIntegrity(): array
    {
        $integrity = [];
        
        // Check for orphaned records
        $orphanedPasswords = DB::select('
            SELECT COUNT(*) as count 
            FROM password_entries pe 
            LEFT JOIN users u ON pe.user_id = u.id 
            WHERE u.id IS NULL
        ')[0]->count;
        
        $integrity['orphaned_passwords'] = $orphanedPasswords;
        
        // Check for invalid data
        $invalidEmails = DB::select('
            SELECT COUNT(*) as count 
            FROM users 
            WHERE email NOT LIKE "%@%.%"
        ')[0]->count;
        
        $integrity['invalid_emails'] = $invalidEmails;
        
        return $integrity;
    }
    
    private function checkEncryptionStatus(): array
    {
        $encryption = [];
        
        // Check password encryption
        $totalPasswords = DB::table('password_entries')->count();
        $encryptedPasswords = DB::table('password_entries')
            ->where('encrypted_password', '!=', '')
            ->whereNotNull('encrypted_password')
            ->count();
        
        $encryption['total_passwords'] = $totalPasswords;
        $encryption['encrypted_passwords'] = $encryptedPasswords;
        $encryption['encryption_rate'] = $totalPasswords > 0 ? 
            round(($encryptedPasswords / $totalPasswords) * 100, 2) : 100;
        
        return $encryption;
    }
}
