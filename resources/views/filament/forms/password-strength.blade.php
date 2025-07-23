@php
    use App\Services\PasswordSecurityService;
    $service = new PasswordSecurityService();
    $strength = $getState();
    $description = $service->getStrengthDescription($strength);
    $color = $service->getStrengthColor($strength);
@endphp

<div class="space-y-2">
    <div class="flex items-center justify-between">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
            Password Strength
        </span>
        <span class="text-sm font-semibold" style="color: {{ $color }}">
            {{ $description }}
        </span>
    </div>
    
    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
        <div 
            class="h-2 rounded-full transition-all duration-300" 
            style="width: {{ ($strength / 5) * 100 }}%; background-color: {{ $color }}"
        ></div>
    </div>
    
    <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400">
        <span>Very Weak</span>
        <span>Weak</span>
        <span>Fair</span>
        <span>Strong</span>
        <span>Very Strong</span>
    </div>
</div>
