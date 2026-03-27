<div class="space-y-8">
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between gap-3">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Step :current of :total', ['current' => $workflowSummary['current_step_number'], 'total' => $workflowSummary['total_steps']]) }}
                · {{ $workflowSummary['current_step'] }}
            </flux:text>
            <flux:text class="text-sm font-medium tabular-nums">{{ $workflowSummary['progress_percentage'] }}%</flux:text>
        </div>

        <flux:progress :value="$workflowSummary['progress_percentage']" />

        @if ($workspaceGuidance)
            <flux:separator variant="subtle" />

            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Best next area: :tab', ['tab' => $workspaceGuidance['tab']]) }}
                    </flux:text>

                    <div>
                        <flux:heading size="lg">{{ $workspaceGuidance['title'] }}</flux:heading>
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $workspaceGuidance['summary'] }}
                        </flux:text>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-300 lg:max-w-sm">
                    <flux:heading size="sm">{{ __('Do next') }}</flux:heading>
                    <span class="mt-2 block">{{ $workspaceGuidance['action'] }}</span>
                </div>
            </div>
        @endif
    </flux:card>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
        <flux:card class="space-y-2 !p-4">
            @foreach ($review->steps as $step)
                @php
                    $isSelected = $selectedStep && $selectedStep->id === $step->id;
                    $isCurrent = $review->current_step_number === $step->step_number;
                @endphp

                <button
                    type="button"
                    wire:click="selectStep({{ $step->step_number }})"
                    class="flex w-full items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left transition-colors {{ $isSelected ? 'border-accent bg-accent/5 ring-1 ring-accent/20 dark:border-accent dark:bg-accent/10' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60' }}"
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $step->step_number }}.</span>
                            <span class="truncate text-sm font-medium {{ $isSelected ? 'text-accent-content dark:text-accent-content' : 'text-zinc-900 dark:text-white' }}">{{ $step->title }}</span>
                        </div>
                        <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">
                            {{ $step->statusLabel() }}
                            @if ($step->completed_at)
                                · {{ $step->completed_at->toFormattedDateString() }}
                            @elseif ($step->started_at)
                                · {{ __('Started :date', ['date' => $step->started_at->toFormattedDateString()]) }}
                            @endif
                        </span>
                    </div>

                    <div class="flex shrink-0 items-center gap-2">
                        @if ($isCurrent)
                            <flux:badge size="sm" color="violet">{{ __('Current') }}</flux:badge>
                        @endif
                    </div>
                </button>
            @endforeach
        </flux:card>

        @if ($selectedStep)
            <flux:card class="space-y-6">
                <flux:badge size="sm">{{ __('Step :number', ['number' => $selectedStep->step_number]) }}</flux:badge>

                <div>
                    <flux:heading size="lg">{{ $selectedStep->title }}</flux:heading>
                    <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stepContext($selectedStep) }}</flux:text>
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach ($this->stepMetricCards($review, $selectedStep) as $metric)
                        <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</flux:text>
                            <p class="mt-auto pt-3 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $metric['value'] }}</p>
                        </div>
                    @endforeach
                </div>

                <flux:separator variant="subtle" />

                <flux:table>
                    <flux:table.rows>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Status') }}</flux:table.cell>
                            <flux:table.cell>{{ $selectedStep->statusLabel() }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Started') }}</flux:table.cell>
                            <flux:table.cell>{{ $selectedStep->started_at?->toDayDateTimeString() ?? __('Not started') }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Completed') }}</flux:table.cell>
                            <flux:table.cell>{{ $selectedStep->completed_at?->toDayDateTimeString() ?? __('In progress') }}</flux:table.cell>
                        </flux:table.row>
                        <flux:table.row>
                            <flux:table.cell variant="strong">{{ __('Notes') }}</flux:table.cell>
                            <flux:table.cell>{{ $selectedStep->notes ?? __('No notes recorded.') }}</flux:table.cell>
                        </flux:table.row>
                    </flux:table.rows>
                </flux:table>
            </flux:card>
        @endif
    </div>
</div>
