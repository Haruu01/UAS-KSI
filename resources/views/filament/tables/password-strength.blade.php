@php
    use App\Services\PasswordSecurityService;
    $service = new PasswordSecurityService();
    $strength = $getRecord()->password_strength ?? 1;
    $description = $service->getStrengthDescription($strength);
    $color = $service->getStrengthColor($strength);
@endphp

<div class="flex items-center space-x-2">
    <div class="flex space-x-1">
        @for ($i = 1; $i <= 5; $i++)
            <div 
                class="w-2 h-4 rounded-sm {{ $i <= $strength ? '' : 'opacity-30' }}"
                style="background-color: {{ $i <= $strength ? $color : '#E5E7EB' }}"
            ></div>
        @endfor
    </div>
    <span class="text-xs font-medium" style="color: {{ $color }}">
        {{ $description }}
    </span>
</div>
