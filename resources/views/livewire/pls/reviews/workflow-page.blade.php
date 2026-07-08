<section class="rounded-lg border border-zinc-200 bg-white text-sm shadow-sm dark:border-zinc-800 dark:bg-zinc-950/30">
    <div class="border-b border-zinc-200 bg-teal-50/80 px-4 py-3 text-teal-950 dark:border-zinc-800 dark:bg-teal-950/20 dark:text-teal-100">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h2 class="text-sm font-semibold">{{ __('PLS process guide') }}</h2>
                    <flux:badge size="sm" color="teal">{{ __('Advisory steps') }}</flux:badge>
                </div>

                <p class="leading-6 text-teal-900/80 dark:text-teal-100/80">
                    {{ __('These numbered steps summarize WFD methodology for planning and checking a PLS review. They are guidance for the inquiry, not the app navigation.') }}
                </p>
            </div>

            <p class="max-w-sm leading-6 text-teal-900/80 dark:text-teal-100/80">
                {{ __('Use the Workspace navigation menu on the left to open Legislation, Evidence, Stakeholders, Consultations, Analysis, Reports, and Settings.') }}
            </p>
        </div>
    </div>

    <div class="divide-y divide-zinc-200 dark:divide-zinc-800">
        @foreach ($review->steps as $step)
            @php
                $isCurrent = $review->current_step_number === $step->step_number;
            @endphp

            <div
                wire:key="workflow-step-{{ $step->id }}"
                @class([
                    'cursor-default px-4 py-3',
                    'bg-violet-50/80 dark:bg-violet-950/20' => $isCurrent,
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
                            <h3
                                @class([
                                    'text-sm leading-5',
                                    'font-semibold text-zinc-950 dark:text-white' => $isCurrent,
                                    'font-medium text-zinc-900 dark:text-zinc-100' => ! $isCurrent,
                                ])
                            >
                                {{ $step->title }}
                            </h3>

                            @if ($isCurrent)
                                <flux:badge size="sm" color="violet">{{ __('Current focus') }}</flux:badge>
                            @endif
                        </div>

                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stepContext($step) }}</p>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</section>
