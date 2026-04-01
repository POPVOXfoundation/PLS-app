@php
    $primaryNavigation = [
        [
            'label' => __('Dashboard'),
            'route' => route('dashboard'),
            'current' => request()->routeIs('dashboard'),
            'icon' => 'home',
        ],
        [
            'label' => __('PLS Reviews'),
            'route' => route('pls.reviews.index'),
            'current' => request()->routeIs('pls.reviews.*'),
            'icon' => 'clipboard-document-list',
        ],
    ];
@endphp

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-950 antialiased dark:bg-zinc-950">
        <div class="min-h-screen">
            <flux:header class="sticky top-0 z-40 border-b border-zinc-200/80 bg-white/92 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/92">
                <div class="mx-auto flex w-full max-w-[1600px] items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <div class="flex min-w-0 items-center gap-5">
                        <x-app-logo href="{{ route('dashboard') }}" wire:navigate class="shrink-0" />

                        <flux:navbar class="hidden md:flex items-center gap-1">
                            @foreach ($primaryNavigation as $item)
                                <flux:navbar.item
                                    :icon="$item['icon']"
                                    :href="$item['route']"
                                    :current="$item['current']"
                                    wire:navigate
                                >
                                    {{ $item['label'] }}
                                </flux:navbar.item>
                            @endforeach
                        </flux:navbar>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        <flux:tooltip :content="__('Toggle dark mode')" position="bottom">
                            <flux:button
                                x-data
                                x-on:click="$flux.dark = ! $flux.dark"
                                icon="moon"
                                variant="subtle"
                                aria-label="{{ __('Toggle dark mode') }}"
                                class="!h-10 [&>svg]:size-5"
                            />
                        </flux:tooltip>

                        <x-desktop-user-menu />
                    </div>
                </div>

                <div class="border-t border-zinc-200/80 md:hidden dark:border-zinc-800">
                    <div class="mx-auto flex w-full max-w-[1600px] items-center gap-2 overflow-x-auto px-4 py-2 sm:px-6">
                        @foreach ($primaryNavigation as $item)
                            <flux:button
                                :href="$item['route']"
                                wire:navigate
                                size="sm"
                                :variant="$item['current'] ? 'primary' : 'ghost'"
                                :icon="$item['icon']"
                                class="shrink-0"
                            >
                                {{ $item['label'] }}
                            </flux:button>
                        @endforeach
                    </div>
                </div>
            </flux:header>

            <flux:main class="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-[1600px] flex-col px-4 py-5 sm:px-6 sm:py-6 lg:px-8">
                {{ $slot }}
            </flux:main>

            @persist('toast')
                <div x-data x-on:app-toast.window="$flux.toast($event.detail.toast ?? $event.detail)">
                    <flux:toast.group position="top end" class="pt-20 sm:pt-24">
                        <flux:toast />
                    </flux:toast.group>
                </div>
            @endpersist

            @if (session()->has('toast'))
                <div
                    x-data
                    x-init="queueMicrotask(() => window.dispatchEvent(new CustomEvent('app-toast', { detail: @js(session('toast')) })))"
                ></div>
            @endif
        </div>

        @fluxScripts
    </body>
</html>
