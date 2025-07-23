<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Security Overview --}}
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-shield-check class="w-8 h-8 text-green-600 dark:text-green-400" />
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $securityStatus['encrypted_passwords'] ?? 0 }}/{{ $securityStatus['total_passwords'] ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Encrypted Passwords
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-users class="w-8 h-8 text-blue-600 dark:text-blue-400" />
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $securityStatus['active_users'] ?? 0 }}/{{ $securityStatus['total_users'] ?? 0 }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Active Users
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <x-heroicon-o-document-text class="w-8 h-8 text-purple-600 dark:text-purple-400" />
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ number_format($securityStatus['audit_logs_count'] ?? 0) }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Audit Log Entries
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        @if(empty($integrityIssues))
                            <x-heroicon-o-check-circle class="w-8 h-8 text-green-600 dark:text-green-400" />
                        @else
                            <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-red-600 dark:text-red-400" />
                        @endif
                    </div>
                    <div class="ml-4">
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ count($integrityIssues) }}
                        </div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Integrity Issues
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Key Management Status --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Encryption Key Status
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <dl class="space-y-2">
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Last Rotation:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $keyStatus['last_rotation'] ? $keyStatus['last_rotation']->format('M j, Y') : 'Never' }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Days Since Rotation:</dt>
                            <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $keyStatus['days_since_rotation'] ?? 'N/A' }}
                            </dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-sm text-gray-600 dark:text-gray-400">Needs Rotation:</dt>
                            <dd class="text-sm font-medium">
                                @if($keyStatus['needs_rotation'] ?? false)
                                    <span class="text-red-600 dark:text-red-400">Yes</span>
                                @else
                                    <span class="text-green-600 dark:text-green-400">No</span>
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>
                
                <div>
                    @if($keyStatus['needs_rotation'] ?? false)
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                            <div class="flex">
                                <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-red-600 dark:text-red-400 mr-2" />
                                <div>
                                    <h4 class="text-sm font-medium text-red-900 dark:text-red-100">
                                        Key Rotation Required
                                    </h4>
                                    <p class="text-sm text-red-700 dark:text-red-300 mt-1">
                                        Encryption key should be rotated for security best practices.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4">
                            <div class="flex">
                                <x-heroicon-o-check-circle class="w-5 h-5 text-green-600 dark:text-green-400 mr-2" />
                                <div>
                                    <h4 class="text-sm font-medium text-green-900 dark:text-green-100">
                                        Key Status Good
                                    </h4>
                                    <p class="text-sm text-green-700 dark:text-green-300 mt-1">
                                        Encryption key is current and secure.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        
        {{-- Integrity Issues --}}
        @if(!empty($integrityIssues))
            <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Database Integrity Issues
                </h3>
                
                <div class="space-y-3">
                    @foreach($integrityIssues as $issue)
                        <div class="flex items-start">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3 mt-0.5 flex-shrink-0" />
                            <div class="text-sm text-gray-700 dark:text-gray-300">
                                {{ $issue }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        
        {{-- Security Recommendations --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Security Recommendations
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                        Data Protection
                    </h4>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Regular database backups
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Encrypt all sensitive data
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Monitor data integrity
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Implement data retention policies
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                        Access Control
                    </h4>
                    <ul class="space-y-1 text-sm text-gray-600 dark:text-gray-400">
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Enable 2FA for all users
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Regular access reviews
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Monitor failed login attempts
                        </li>
                        <li class="flex items-start">
                            <x-heroicon-o-check-circle class="w-4 h-4 text-green-500 mr-2 mt-0.5 flex-shrink-0" />
                            Implement least privilege principle
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        
        {{-- Security Metrics --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Security Metrics
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="text-center">
                    <div class="text-3xl font-bold text-green-600 dark:text-green-400">
                        {{ round(($securityStatus['encrypted_passwords'] / max($securityStatus['total_passwords'], 1)) * 100) }}%
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Data Encryption Rate
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                        {{ round(($securityStatus['active_users'] / max($securityStatus['total_users'], 1)) * 100) }}%
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Active User Rate
                    </div>
                </div>
                
                <div class="text-center">
                    <div class="text-3xl font-bold {{ empty($integrityIssues) ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                        {{ empty($integrityIssues) ? '100' : (100 - count($integrityIssues) * 10) }}%
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Data Integrity Score
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
