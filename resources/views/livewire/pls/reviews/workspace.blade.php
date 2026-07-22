<x-layouts::app.header :title="$title ?? null">
    <div class="flex h-full w-full flex-1 flex-col gap-4">
        <div class="grid flex-1 gap-5 xl:grid-cols-[13rem_minmax(0,1fr)] 2xl:grid-cols-[14rem_minmax(0,1fr)]">
            <aside class="min-w-0 xl:sticky xl:top-24 xl:self-start">
                <nav aria-label="{{ __('Review workspace sections') }}" class="border-b border-zinc-200 pb-2 dark:border-zinc-200 xl:border-b-0 xl:border-s xl:pb-0 xl:ps-3">
                    <div class="mb-3 hidden space-y-1 px-2.5 xl:block">
                        <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Workspace navigation') }}</p>
                        <p class="text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ __('Move through the app with these sections.') }}</p>
                    </div>

                    <div class="flex gap-2 overflow-x-auto pb-1 xl:block xl:space-y-1 xl:overflow-visible xl:pb-0">
                        @foreach ($workspaceNavigation as $workspace)
                            @php
                                $isActive = request()->routeIs($workspace['route']);
                            @endphp

                            <a
                                href="{{ route($workspace['route'], ['review' => $review]) }}"
                                wire:navigate
                                aria-current="{{ $isActive ? 'page' : 'false' }}"
                                title="{{ __('Open :section', ['section' => $workspace['label']]) }}"
                                @class([
                                    'flex shrink-0 items-center gap-2 rounded-lg border border-transparent px-2.5 py-2 text-left text-sm font-medium transition xl:w-full',
                                    'border-violet-200 bg-violet-50 font-semibold text-violet-900 dark:border-violet-200 dark:bg-violet-50 dark:text-violet-900' => $isActive,
                                    'text-zinc-500 hover:border-zinc-200 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-500 dark:hover:border-zinc-200 dark:hover:bg-zinc-50 dark:hover:text-zinc-900' => ! $isActive,
                                ])
                            >
                                <flux:icon :icon="$workspace['icon']" class="size-4 shrink-0" />
                                <span class="min-w-0 flex-1">{{ $workspace['label'] }}</span>
                                @if ($workspace['count'] !== null)
                                    <span class="rounded-md bg-zinc-100 px-1.5 py-0.5 text-xs tabular-nums text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300">{{ $workspace['count'] }}</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    <div class="mt-5 hidden border-t border-zinc-200 px-2.5 pt-4 xl:block dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-3 text-xs">
                            <span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Review progress') }}</span>
                            <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $workflowSummary['progress_percentage'] }}%</span>
                        </div>
                        <flux:progress class="mt-2" :value="$workflowSummary['progress_percentage']" />
                        <p class="mt-2 text-xs leading-5 text-zinc-500 dark:text-zinc-400">
                            {{ __('Step :current of :total', ['current' => $workflowSummary['current_step_number'], 'total' => $workflowSummary['total_steps']]) }}
                        </p>
                    </div>
                </nav>
            </aside>

            <div class="min-w-0 space-y-5">
                <header class="border-b border-zinc-200/80 pb-4 dark:border-zinc-800">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="xl" level="1" class="max-w-full break-words">{{ $review->title }}</flux:heading>
                                <flux:badge size="sm">{{ $review->statusLabel() }}</flux:badge>
                            </div>

                            @if ($review->description)
                                <flux:text class="max-w-4xl text-sm text-zinc-600 dark:text-zinc-400">{{ $review->description }}</flux:text>
                            @endif

                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ __('Current step: :step', ['step' => $workflowSummary['current_step']]) }}</span>
                                <span aria-hidden="true">•</span>
                                <span>{{ __(':progress% complete', ['progress' => $workflowSummary['progress_percentage']]) }}</span>
                            </div>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
                                {{ __('All reviews') }}
                            </flux:button>
                        </div>
                    </div>
                </header>

                <main class="min-w-0 space-y-6 pb-44 sm:pb-40 xl:pb-8 xl:pe-[23.5rem]">
                    {{ $slot }}
                </main>
            </div>
        </div>

        <livewire:pls.reviews.assistant-sidebar
            :review="$review"
            :workspace-key="$currentWorkspaceKey"
            :wire:key="'assistant-'.$review->getKey().'-'.$currentWorkspaceKey"
        />
    </div>
</x-layouts::app.header>
