<?php

namespace App\Console\Commands;

use App\Services\DatabaseHardeningService;
use App\Services\DatabaseSecurityService;
use App\Services\KeyManagementService;
use Illuminate\Console\Command;

class SecurityHardeningCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:harden {--verify : Verify current security status}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Apply comprehensive security hardening to the application';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🛡️  Password Manager Security Hardening');
        $this->info('=' . str_repeat('=', 50));

        if ($this->option('verify')) {
            return $this->verifySecurityStatus();
        }

        return $this->applySecurityHardening();
    }

    private function applySecurityHardening(): int
    {
        $this->info('🔧 Applying security hardening...');

        // Database hardening
        $this->info('📊 Hardening database...');
        $dbHardening = new DatabaseHardeningService();
        $dbResults = $dbHardening->hardenDatabase();

        foreach ($dbResults as $component => $result) {
            $resultStr = is_array($result) ? json_encode($result) : $result;
            if ($result === 'Applied' || $result === 'Created' || $result === 'Implemented') {
                $this->info("  ✅ {$component}: {$resultStr}");
            } else {
                $this->warn("  ⚠️  {$component}: {$resultStr}");
            }
        }

        // Key management check
        $this->info('🔑 Checking key management...');
        $keyService = new KeyManagementService();
        $keyStatus = $keyService->getKeyRotationStatus();

        if ($keyStatus['needs_rotation']) {
            $this->warn('  ⚠️  Encryption key needs rotation');

            if ($this->confirm('Do you want to backup the current key?')) {
                if ($keyService->backupCurrentKey()) {
                    $this->info('  ✅ Key backed up successfully');
                } else {
                    $this->error('  ❌ Key backup failed');
                }
            }
        } else {
            $this->info('  ✅ Key management: Current');
        }

        // Database security check
        $this->info('🔒 Applying database security...');
        $dbSecurity = new DatabaseSecurityService();
        $securityStatus = $dbSecurity->getSecurityStatus();

        $encryptionRate = ($securityStatus['encrypted_passwords'] / max($securityStatus['total_passwords'], 1)) * 100;

        if ($encryptionRate < 100) {
            $this->warn("  ⚠️  Only {$encryptionRate}% of passwords are encrypted");

            if ($this->confirm('Do you want to encrypt all passwords?')) {
                if ($dbSecurity->encryptSensitiveColumns()) {
                    $this->info('  ✅ All passwords encrypted');
                } else {
                    $this->error('  ❌ Password encryption failed');
                }
            }
        } else {
            $this->info('  ✅ All passwords properly encrypted');
        }

        // Integrity check
        $this->info('🔍 Running integrity check...');
        $integrityIssues = $dbSecurity->verifyDatabaseIntegrity();

        if (empty($integrityIssues)) {
            $this->info('  ✅ Database integrity: Perfect');
        } else {
            $this->warn('  ⚠️  Integrity issues found:');
            foreach ($integrityIssues as $issue) {
                $this->warn("    - {$issue}");
            }
        }

        // Security recommendations
        $this->info('📋 Security recommendations:');
        $this->info('  • Enable HTTPS in production');
        $this->info('  • Set up regular database backups');
        $this->info('  • Monitor audit logs regularly');
        $this->info('  • Update dependencies monthly');
        $this->info('  • Rotate encryption keys quarterly');
        $this->info('  • Review user access permissions');

        $this->info('');
        $this->info('🎉 Security hardening completed!');

        return Command::SUCCESS;
    }

    private function verifySecurityStatus(): int
    {
        $this->info('🔍 Verifying security status...');

        // Database hardening verification
        $dbHardening = new DatabaseHardeningService();
        $verification = $dbHardening->verifyDatabaseSecurity();

        $this->info('📊 Database Security Status:');
        $this->info("  • Triggers: {$verification['triggers_count']}");
        $this->info("  • Views: {$verification['views_count']}");
        $this->info("  • Encryption Rate: {$verification['encryption_status']['encryption_rate']}%");

        if ($verification['data_integrity']['orphaned_passwords'] > 0) {
            $this->warn("  ⚠️  Orphaned passwords: {$verification['data_integrity']['orphaned_passwords']}");
        }

        if ($verification['data_integrity']['invalid_emails'] > 0) {
            $this->warn("  ⚠️  Invalid emails: {$verification['data_integrity']['invalid_emails']}");
        }

        // Key management status
        $keyService = new KeyManagementService();
        $keyStatus = $keyService->getKeyRotationStatus();

        $this->info('🔑 Key Management Status:');
        $this->info("  • Last Rotation: " . ($keyStatus['last_rotation'] ? $keyStatus['last_rotation']->format('Y-m-d') : 'Never'));
        $this->info("  • Days Since Rotation: " . ($keyStatus['days_since_rotation'] ?? 'N/A'));
        $this->info("  • Needs Rotation: " . ($keyStatus['needs_rotation'] ? 'Yes' : 'No'));

        // Security score calculation
        $score = $this->calculateSecurityScore($verification, $keyStatus);
        $this->info('');
        $this->info("🏆 Overall Security Score: {$score}/100");

        if ($score >= 90) {
            $this->info('✅ Excellent security posture!');
        } elseif ($score >= 80) {
            $this->info('✅ Good security posture');
        } elseif ($score >= 70) {
            $this->warn('⚠️  Moderate security - improvements needed');
        } else {
            $this->error('❌ Poor security - immediate action required');
        }

        return Command::SUCCESS;
    }

    private function calculateSecurityScore(array $verification, array $keyStatus): int
    {
        $score = 100;

        // Deduct for encryption issues
        if ($verification['encryption_status']['encryption_rate'] < 100) {
            $score -= (100 - $verification['encryption_status']['encryption_rate']);
        }

        // Deduct for data integrity issues
        if ($verification['data_integrity']['orphaned_passwords'] > 0) {
            $score -= 10;
        }

        if ($verification['data_integrity']['invalid_emails'] > 0) {
            $score -= 5;
        }

        // Deduct for key management issues
        if ($keyStatus['needs_rotation']) {
            $score -= 15;
        }

        // Deduct for missing security features
        if ($verification['triggers_count'] < 5) {
            $score -= 10;
        }

        if ($verification['views_count'] < 3) {
            $score -= 5;
        }

        return max(0, $score);
    }
}
