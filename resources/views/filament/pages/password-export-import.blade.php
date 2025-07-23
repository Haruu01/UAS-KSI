<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Export Section --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex items-center mb-4">
                <x-heroicon-o-arrow-down-tray class="w-6 h-6 text-green-600 dark:text-green-400 mr-3" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Export Passwords
                </h3>
            </div>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Export all your passwords and categories to an encrypted JSON file. This file can be used as a backup or to import into another account.
            </p>
            
            <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-blue-900 dark:text-blue-100 mb-2">
                    🔐 Security Features
                </h4>
                <ul class="text-sm text-blue-800 dark:text-blue-200 space-y-1">
                    <li>• All data is encrypted with AES-256 encryption</li>
                    <li>• Your export password is required to decrypt the file</li>
                    <li>• Passwords are exported in their original form (decrypted)</li>
                    <li>• File includes categories and metadata</li>
                </ul>
            </div>
            
            <form wire:submit="exportPasswords">
                <div class="max-w-md">
                    {{ $this->form->getComponent('exportPassword') }}
                </div>
                
                <div class="mt-4">
                    <x-filament::button type="submit" color="success" icon="heroicon-o-arrow-down-tray">
                        Export Passwords
                    </x-filament::button>
                </div>
            </form>
        </div>
        
        {{-- Import Section --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <div class="flex items-center mb-4">
                <x-heroicon-o-arrow-up-tray class="w-6 h-6 text-blue-600 dark:text-blue-400 mr-3" />
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                    Import Passwords
                </h3>
            </div>
            
            <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                Import passwords from an encrypted JSON file. The file must be created by this application's export feature.
            </p>
            
            <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-4">
                <h4 class="font-medium text-yellow-900 dark:text-yellow-100 mb-2">
                    ⚠️ Import Notes
                </h4>
                <ul class="text-sm text-yellow-800 dark:text-yellow-200 space-y-1">
                    <li>• Existing passwords with the same title will be skipped</li>
                    <li>• Categories will be created if they don't exist</li>
                    <li>• You need the password used during export</li>
                    <li>• Maximum file size: 10MB</li>
                </ul>
            </div>
            
            <form wire:submit="importPasswords">
                <div class="space-y-4 max-w-md">
                    {{ $this->form->getComponent('importFile') }}
                    {{ $this->form->getComponent('importPassword') }}
                </div>
                
                <div class="mt-4">
                    <x-filament::button type="submit" color="info" icon="heroicon-o-arrow-up-tray">
                        Import Passwords
                    </x-filament::button>
                </div>
            </form>
        </div>
        
        {{-- Statistics --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Current Statistics
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ auth()->user()->passwordEntries()->count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Total Passwords
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ auth()->user()->categories()->count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Categories
                    </div>
                </div>
                
                <div class="bg-gray-50 dark:bg-gray-700 p-4 rounded-lg">
                    <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                        {{ auth()->user()->sharedPasswordsGiven()->where('is_active', true)->count() }}
                    </div>
                    <div class="text-sm text-gray-600 dark:text-gray-400">
                        Shared Passwords
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Security Guidelines --}}
        <div class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                Security Guidelines
            </h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                        Export Security
                    </h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li>• Use a strong, unique password for export</li>
                        <li>• Store exported files in a secure location</li>
                        <li>• Delete exported files after use</li>
                        <li>• Don't share export passwords</li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                        Import Security
                    </h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                        <li>• Only import files from trusted sources</li>
                        <li>• Verify file integrity before import</li>
                        <li>• Review imported passwords for accuracy</li>
                        <li>• Update weak passwords after import</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
