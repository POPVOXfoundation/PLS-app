@props([
    'sidebar' => false,
])

@if($sidebar)
    <a {{ $attributes->merge(['class' => 'flex items-center']) }}>
        <img src="{{ asset('images/pls.png') }}" alt="{{ config('app.name') }}" class="h-9 w-auto dark:hidden" />
        <img src="{{ asset('images/pls_dark.png') }}" alt="{{ config('app.name') }}" class="hidden h-9 w-auto dark:block" />
    </a>
@else
    <a {{ $attributes->merge(['class' => 'flex items-center']) }}>
        <img src="{{ asset('images/pls.png') }}" alt="{{ config('app.name') }}" class="mt-1 h-11 w-auto dark:hidden" />
        <img src="{{ asset('images/pls_dark.png') }}" alt="{{ config('app.name') }}" class="mt-1 hidden h-11 w-auto dark:block" />
    </a>
@endif
