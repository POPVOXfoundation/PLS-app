<div class="space-y-2">
    @foreach ($review->steps as $step)
        @php
            $isCurrent = $review->current_step_number === $step->step_number;
        @endphp

        <div
            wire:key="workflow-step-{{ $step->id }}"
            @class([
                'rounded-lg border px-4 py-3',
                'border-violet-300 bg-violet-50 dark:border-violet-800 dark:bg-violet-950/20' => $isCurrent,
                'border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-950/30' => ! $isCurrent,
            ])
        >
            <div class="flex items-start gap-3">
                <span
                    @class([
                        'inline-flex size-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold tabular-nums',
                        'border-violet-300 bg-violet-100 text-violet-900 dark:border-violet-700 dark:bg-violet-900/50 dark:text-violet-100' => $isCurrent,
                        'border-zinc-200 text-zinc-500 dark:border-zinc-700 dark:text-zinc-400' => ! $isCurrent,
                    ])
                >
                    {{ $step->step_number }}
                </span>

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2
                            @class([
                                'text-sm leading-5',
                                'font-semibold text-zinc-950 dark:text-white' => $isCurrent,
                                'font-medium text-zinc-900 dark:text-zinc-100' => ! $isCurrent,
                            ])
                        >
                            {{ $step->title }}
                        </h2>

                        @if ($isCurrent)
                            <flux:badge size="sm" color="violet">{{ __('Current') }}</flux:badge>
                        @endif
                    </div>

                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stepContext($step) }}</p>
                </div>
            </div>
        </div>
    @endforeach
</div>
