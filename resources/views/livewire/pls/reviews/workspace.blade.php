<x-layouts::app.header :title="$title ?? null">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-200">
                {{ session('status') }}
            </div>
        @endif

        <div class="grid flex-1 gap-6 xl:grid-cols-[11rem_minmax(0,1fr)] 2xl:grid-cols-[12rem_minmax(0,1fr)]">
            <aside class="xl:sticky xl:top-24 xl:self-start">
                <nav aria-label="{{ __('Review sections') }}" class="border-s border-zinc-200 ps-3 dark:border-zinc-200">
                    <div class="space-y-1">
                        @foreach ($workspaceNavigation as $workspace)
                            @php
                                $isActive = request()->routeIs($workspace['route']);
                            @endphp

                            <a
                                href="{{ route($workspace['route'], ['review' => $review]) }}"
                                wire:navigate
                                aria-current="{{ $isActive ? 'page' : 'false' }}"
                                @class([
                                    'flex w-full items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium transition',
                                    'border-violet-200 bg-violet-50 font-semibold text-violet-900 dark:border-violet-200 dark:bg-violet-50 dark:text-violet-900' => $isActive,
                                    'text-zinc-500 hover:border-zinc-200 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-500 dark:hover:border-zinc-200 dark:hover:bg-zinc-50 dark:hover:text-zinc-900' => ! $isActive,
                                ])
                            >
                                <flux:icon :icon="$workspace['icon']" class="size-4 shrink-0" />
                                <span>{{ $workspace['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </nav>
            </aside>

            <div class="min-w-0 space-y-5">
                <header class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="xl" level="1">{{ $review->title }}</flux:heading>
                                <flux:badge size="sm">{{ $review->statusLabel() }}</flux:badge>
                            </div>

                            @if ($review->description)
                                <flux:text class="max-w-4xl text-sm text-zinc-600 dark:text-zinc-400">{{ $review->description }}</flux:text>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
                                {{ __('All reviews') }}
                            </flux:button>
                            <flux:button variant="primary" icon="plus" :href="route('pls.reviews.create')" wire:navigate>
                                {{ __('New review') }}
                            </flux:button>
                        </div>
                    </div>
                </header>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem] 2xl:grid-cols-[minmax(0,1fr)_26rem]">
                    <main class="min-w-0 space-y-6">
                        {{ $slot }}
                    </main>

                    <aside class="xl:sticky xl:top-24 xl:self-start">
                        <livewire:pls.reviews.assistant-sidebar
                            :review="$review"
                            :workspace-key="$currentWorkspaceKey"
                            :wire:key="'assistant-'.$review->getKey().'-'.$currentWorkspaceKey"
                        />
                    </aside>
                </div>
            </div>
        </div>
    </div>
</x-layouts::app.header>
