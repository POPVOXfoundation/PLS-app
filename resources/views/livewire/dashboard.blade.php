@php
    $totalReviews = array_sum(array_column($assignmentSummaries, 'reviews_count'));
    $topAssignmentSummaries = array_slice($assignmentSummaries, 0, 4);
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- ── Header ── --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Dashboard') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Post-legislative scrutiny portfolio overview.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button variant="primary" icon="plus" :href="route('pls.reviews.create')" wire:navigate>
                {{ __('New review') }}
            </flux:button>
            <flux:button variant="ghost" :href="route('pls.reviews.index')" wire:navigate>
                {{ __('All reviews') }}
            </flux:button>
        </div>
    </div>

    {{-- ── Key figures ── --}}
    <div class="grid grid-cols-3 gap-4">
        <flux:card class="flex items-start gap-4">
            <div class="rounded-lg bg-zinc-100 p-2.5 dark:bg-zinc-800">
                <flux:icon.document-text class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="space-y-1">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Total reviews') }}</flux:text>
                <p class="text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $totalReviews }}</p>
                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Across the full review portfolio') }}</flux:text>
            </div>
        </flux:card>
        <flux:card class="flex items-start gap-4">
            <div class="rounded-lg bg-zinc-100 p-2.5 dark:bg-zinc-800">
                <flux:icon.bolt class="size-5 text-zinc-500 dark:text-zinc-400" />
            </div>
            <div class="space-y-1">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Active') }}</flux:text>
                <p class="text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $heroChips[0]['value'] ?? '0' }}</p>
                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ $heroChips[0]['detail'] }}</flux:text>
            </div>
        </flux:card>
        <flux:card class="flex items-start gap-4 {{ ($heroChips[2]['value'] ?? 0) > 0 ? 'border-red-200 dark:border-red-900/40' : '' }}">
            <div class="rounded-lg {{ ($heroChips[2]['value'] ?? 0) > 0 ? 'bg-red-50 dark:bg-red-950/30' : 'bg-zinc-100 dark:bg-zinc-800' }} p-2.5">
                <flux:icon.exclamation-triangle class="size-5 {{ ($heroChips[2]['value'] ?? 0) > 0 ? 'text-red-500 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}" />
            </div>
            <div class="space-y-1">
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Needs attention') }}</flux:text>
                <p class="text-2xl font-semibold tabular-nums {{ ($heroChips[2]['value'] ?? 0) > 0 ? 'text-red-600 dark:text-red-400' : 'text-zinc-950 dark:text-white' }}">
                    {{ $heroChips[2]['value'] ?? '0' }}
                </p>
                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ $heroChips[2]['detail'] }}</flux:text>
            </div>
        </flux:card>
    </div>

    {{-- ── Pipeline ── --}}
    <flux:card class="space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg" level="2">{{ __('Workflow pipeline') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Distribution of reviews across the scrutiny lifecycle.') }}
            </flux:text>
        </div>

        <flux:table class="[&_table]:table-fixed">
            <flux:table.columns>
                <flux:table.column class="w-[30%]">{{ __('Phase') }}</flux:table.column>
                <flux:table.column class="w-[12%]">{{ __('Reviews') }}</flux:table.column>
                <flux:table.column class="w-[12%]">{{ __('Active') }}</flux:table.column>
                <flux:table.column class="w-[12%]">{{ __('Completed') }}</flux:table.column>
                <flux:table.column class="w-[34%] pl-8">{{ __('Share') }}</flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($phasePipeline as $phase)
                    <flux:table.row :key="$phase['key']">
                        <flux:table.cell variant="strong">{{ $phase['label'] }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $phase['reviews_count'] }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $phase['active_count'] }}</flux:table.cell>
                        <flux:table.cell class="tabular-nums">{{ $phase['completed_count'] }}</flux:table.cell>
                        <flux:table.cell class="pl-8">
                            <div class="flex items-center gap-2">
                                <div class="h-1.5 w-20 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-zinc-900 dark:bg-zinc-300" style="width: {{ $phase['ratio'] }}%;"></div>
                                </div>
                                <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $phase['ratio'] }}%</span>
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>

    {{-- ── Needs attention ── --}}
    <flux:card class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="space-y-1">
                <flux:heading size="lg" level="2">{{ __('Needs attention') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Reviews requiring follow-up or action.') }}
                </flux:text>
            </div>
            @if (count($attentionReviews) > 0)
                <flux:badge size="sm">{{ count($attentionReviews) }}</flux:badge>
            @endif
        </div>

        @if ($attentionReviews === [])
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('All reviews are progressing normally.') }}
            </flux:text>
        @else
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                @foreach ($attentionReviews as $item)
                    <div class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                        <div class="min-w-0 space-y-1">
                            <div class="flex items-center gap-2">
                                <flux:link :href="route('pls.reviews.workflow', ['review' => $item['review_id']])" wire:navigate variant="subtle" class="truncate text-sm font-medium">
                                    {{ $item['title'] }}
                                </flux:link>
                                <flux:badge size="sm" :color="$item['tone'] === 'urgent' || $item['tone'] === 'draft' ? 'red' : 'zinc'">
                                    {{ $item['urgency_label'] }}
                                </flux:badge>
                            </div>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $item['assignment_name'] }} · {{ $item['phase'] }} · {{ $item['current_step'] }}
                            </flux:text>
                        </div>
                        <span class="shrink-0 text-sm tabular-nums text-zinc-400 dark:text-zinc-500">{{ $item['progress'] }}%</span>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    {{-- ── Bottom two-column ── --}}
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(320px,1fr)]">

        {{-- Recent reviews --}}
        <flux:card class="space-y-4">
            <div class="flex items-center justify-between">
                <flux:heading size="lg" level="2">{{ __('Recent reviews') }}</flux:heading>
                <flux:button variant="ghost" size="sm" :href="route('pls.reviews.index')" wire:navigate>
                    {{ __('View all') }}
                </flux:button>
            </div>

            @if ($recentReviews->isEmpty())
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No reviews yet.') }}
                </flux:text>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                    @foreach ($recentReviews as $review)
                        <div class="flex items-center gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0 flex-1 space-y-0.5">
                                <div class="flex items-center gap-2">
                                    <flux:link :href="route('pls.reviews.workflow', ['review' => $review->id])" wire:navigate variant="subtle" class="truncate text-sm font-medium">
                                        {{ $review->title }}
                                    </flux:link>
                                    <flux:badge size="sm">{{ $review->statusLabel() }}</flux:badge>
                                </div>
                                <flux:text class="truncate text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $this->reviewAssignmentLabel($review) }} · {{ $review->currentStepTitle() }}
                                </flux:text>
                            </div>

                            <div class="hidden shrink-0 items-center gap-2 sm:flex">
                                <div class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-zinc-900 dark:bg-zinc-300" style="width: {{ $review->progressPercentage() }}%;"></div>
                                </div>
                                <span class="w-8 text-right text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $review->progressPercentage() }}%</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>

        {{-- Assignment workload --}}
        <flux:card class="space-y-4">
            <flux:heading size="lg" level="2">{{ __('Assignment workload') }}</flux:heading>

            @if ($topAssignmentSummaries === [])
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No assignment activity yet.') }}
                </flux:text>
            @else
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                    @foreach ($topAssignmentSummaries as $summary)
                        <div class="space-y-2 py-3 first:pt-0 last:pb-0">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $summary['assignment_name'] }}</p>
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $summary['legislature_name'] }}</flux:text>
                                </div>
                                <p class="shrink-0 text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">
                                    {{ trans_choice('{1} :count review|[2,*] :count reviews', $summary['reviews_count'], ['count' => $summary['reviews_count']]) }}
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                    <div class="h-full rounded-full bg-zinc-900 dark:bg-zinc-300" style="width: {{ $summary['average_progress'] }}%;"></div>
                                </div>
                                <span class="w-8 text-right text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $summary['average_progress'] }}%</span>
                            </div>

                            <div class="flex gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                                <span>{{ $summary['active_reviews_count'] }} {{ __('active') }}</span>
                                @if ($summary['attention_reviews_count'] > 0)
                                    <span class="text-red-600 dark:text-red-400">{{ trans_choice('{1} :count needs attention|[2,*] :count need attention', $summary['attention_reviews_count'], ['count' => $summary['attention_reviews_count']]) }}</span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>
</div>
