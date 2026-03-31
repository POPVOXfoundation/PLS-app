<div class="space-y-8">
    <flux:card class="!p-4">
        <flux:accordion>
            @foreach ($review->steps as $step)
                @php
                    $isCurrent = $review->current_step_number === $step->step_number;
                @endphp

                <flux:accordion.item :expanded="$isCurrent" wire:key="workflow-step-{{ $step->id }}">
                    <flux:accordion.heading>
                        <div class="flex min-w-0 items-start gap-3">
                            <span class="mt-0.5 text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $step->step_number }}.</span>

                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $step->title }}</span>

                                    @if ($isCurrent)
                                        <flux:badge size="sm" color="violet">{{ __('Current') }}</flux:badge>
                                    @endif
                                </div>

                                <span class="mt-1 block text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $step->statusLabel() }}
                                    @if ($step->completed_at)
                                        · {{ $step->completed_at->toFormattedDateString() }}
                                    @elseif ($step->started_at)
                                        · {{ __('Started :date', ['date' => $step->started_at->toFormattedDateString()]) }}
                                    @endif
                                </span>
                            </div>
                        </div>
                    </flux:accordion.heading>

                    <flux:accordion.content>
                        <div class="space-y-6 rounded-xl border border-zinc-200 bg-zinc-50/40 p-4 dark:border-zinc-800 dark:bg-zinc-900/40">
                            <div class="space-y-3">
                                <flux:badge size="sm">{{ __('Step :number', ['number' => $step->step_number]) }}</flux:badge>

                                <div>
                                    <flux:heading size="lg">{{ $step->title }}</flux:heading>
                                    <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stepContext($step) }}</flux:text>
                                </div>
                            </div>

                            <div class="grid gap-4 sm:grid-cols-3">
                                @foreach ($this->stepMetricCards($review, $step) as $metric)
                                    <div class="flex flex-col rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950/40">
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
                                        <flux:table.cell>{{ $step->statusLabel() }}</flux:table.cell>
                                    </flux:table.row>
                                    <flux:table.row>
                                        <flux:table.cell variant="strong">{{ __('Started') }}</flux:table.cell>
                                        <flux:table.cell>{{ $step->started_at?->toDayDateTimeString() ?? __('Not started') }}</flux:table.cell>
                                    </flux:table.row>
                                    <flux:table.row>
                                        <flux:table.cell variant="strong">{{ __('Completed') }}</flux:table.cell>
                                        <flux:table.cell>{{ $step->completed_at?->toDayDateTimeString() ?? __('In progress') }}</flux:table.cell>
                                    </flux:table.row>
                                    <flux:table.row>
                                        <flux:table.cell variant="strong">{{ __('Notes') }}</flux:table.cell>
                                        <flux:table.cell>{{ $step->notes ?? __('No notes recorded.') }}</flux:table.cell>
                                    </flux:table.row>
                                </flux:table.rows>
                            </flux:table>
                        </div>
                    </flux:accordion.content>
                </flux:accordion.item>
            @endforeach
        </flux:accordion>
    </flux:card>
</div>
