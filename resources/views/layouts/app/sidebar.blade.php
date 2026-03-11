<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-100 text-zinc-950 antialiased dark:bg-zinc-950">
        <flux:sidebar sticky collapsible class="border-e border-zinc-300/80 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                <div class="px-3 py-2 in-data-flux-sidebar-collapsed-desktop:hidden">
                    <div class="text-sm font-medium leading-none text-zinc-400">{{ __('Platform') }}</div>
                </div>

                <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                    {{ __('Dashboard') }}
                </flux:sidebar.item>
                <flux:sidebar.item icon="clipboard-document-list" :href="route('pls.reviews.index')" :current="request()->routeIs('pls.reviews.*')" wire:navigate>
                    {{ __('PLS Reviews') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>
        </flux:sidebar>

        <flux:header class="border-b border-zinc-300/80 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/95">
            <div class="flex w-full items-center justify-between gap-3 py-3 pe-3 ps-0 sm:pe-4 sm:ps-0 lg:pe-5 lg:ps-0">
                <div class="flex items-center gap-2">
                    <div
                        x-data="{
                            collapsed: false,
                            sync() {
                                this.collapsed = document.querySelector('[data-flux-sidebar]')?.hasAttribute('data-flux-sidebar-collapsed-desktop') ?? false;
                            },
                        }"
                        x-init="
                            sync();
                            const sidebar = document.querySelector('[data-flux-sidebar]');

                            if (sidebar) {
                                const observer = new MutationObserver(() => sync());

                                observer.observe(sidebar, {
                                    attributes: true,
                                    attributeFilter: ['data-flux-sidebar-collapsed-desktop'],
                                });

                                $el._sidebarObserver = observer;
                            }
                        "
                        x-on:remove.window="$el._sidebarObserver?.disconnect()"
                    >
                        <flux:button
                            variant="subtle"
                            square
                            class="-ms-2.5 shrink-0 lg:-ms-3.5"
                            x-on:click="$dispatch('flux-sidebar-toggle')"
                            aria-label="{{ __('Toggle sidebar') }}"
                            data-flux-sidebar-toggle
                        >
                            <flux:icon icon="panel-left-close" class="size-5" x-show="!collapsed" x-cloak />
                            <flux:icon icon="panel-right-close" class="size-5" x-show="collapsed" x-cloak />
                        </flux:button>
                    </div>

                    <div class="lg:hidden">
                        <x-app-logo href="{{ route('dashboard') }}" wire:navigate />
                    </div>
                </div>

                <div class="flex items-center gap-1">
                    <flux:button x-data x-on:click="$flux.dark = ! $flux.dark" icon="moon" variant="subtle" aria-label="Toggle dark mode" />
                    <x-desktop-user-menu />
                </div>
            </div>
        </flux:header>

        {{ $slot }}

        @fluxScripts
    </body>
</html>
