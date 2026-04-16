@props([
    'sidebar' => false,
])

@if($sidebar)
    <flux:sidebar.brand href="/" class="px-2">
        <img src="{{ asset('img/logo_aicufenos.png') }}" alt="Aicúfenos" class="h-8 w-auto">
    </flux:sidebar.brand>
@else
    <flux:brand href="/" class="px-2">
        <img src="{{ asset('img/logo_aicufenos.png') }}" alt="Aicúfenos" class="h-8 w-auto">
    </flux:brand>
@endif
