<div class="space-y-8">
    <section id="review-details" class="border-b border-zinc-200 pb-6 dark:border-zinc-800">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 space-y-2">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:heading size="lg" level="2">{{ __('Review overview') }}</flux:heading>
                    <flux:badge size="sm" color="violet">{{ __('Workspace home') }}</flux:badge>
                </div>
                <flux:text class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Use this page to check what has been recorded, identify gaps, and move into the next part of the review.') }}
                </flux:text>
            </div>

            @can('update', $review)
                <flux:button variant="ghost" icon="pencil-square" wire:click="prepareReviewEdit">
                    {{ __('Edit review details') }}
                </flux:button>
            @endcan
        </div>

        <div class="mt-5 grid gap-x-8 gap-y-5 lg:grid-cols-[minmax(0,1.45fr)_minmax(16rem,0.8fr)]">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Purpose and scope') }}</p>
                @if (filled($review->description))
                    <p class="max-w-3xl text-sm leading-6 text-zinc-800 dark:text-zinc-200">{{ $review->description }}</p>
                @else
                    <div class="flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('No purpose or scope has been recorded yet.') }}</span>
                        @can('update', $review)
                            <button type="button" wire:click="prepareReviewEdit" class="font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">
                                {{ __('Add details') }}
                            </button>
                        @endcan
                    </div>
                @endif
            </div>

            <dl class="grid grid-cols-2 gap-x-5 gap-y-4 text-sm">
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Inquiry lead') }}</dt>
                    <dd class="mt-1 break-words font-medium text-zinc-900 dark:text-white">{{ $review->assignmentLabel() }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Legislature') }}</dt>
                    <dd class="mt-1 break-words font-medium text-zinc-900 dark:text-white">{{ $review->legislature?->name ?? __('Not recorded') }}</dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</dt>
                    <dd class="mt-1 break-words font-medium text-zinc-900 dark:text-white">
                        {{ $review->assignmentLocationParts() !== [] ? implode(' - ', $review->assignmentLocationParts()) : __('Not recorded') }}
                    </dd>
                </div>
                <div class="min-w-0">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Started') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $review->start_date?->format('j M Y') ?? __('Not recorded') }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(17rem,0.75fr)]">
        <div class="border-b border-zinc-200 pb-6 dark:border-zinc-800 xl:border-b-0 xl:border-e xl:pe-6">
            <div class="flex items-center gap-3">
                <span class="inline-flex size-8 shrink-0 items-center justify-center rounded-full bg-violet-100 text-sm font-semibold text-violet-900 dark:bg-violet-900/50 dark:text-violet-100">
                    {{ $review->current_step_number }}
                </span>
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Current focus') }}</p>
                    <h2 class="mt-0.5 text-base font-semibold text-zinc-950 dark:text-white">{{ $currentAction['title'] }}</h2>
                </div>
            </div>

            <p class="mt-4 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $currentAction['summary'] }}</p>
            <p class="mt-3 max-w-3xl text-sm font-medium leading-6 text-zinc-800 dark:text-zinc-200">{{ $currentAction['action'] }}</p>

            <div class="mt-4">
                <flux:button variant="primary" :href="$currentAction['route']" wire:navigate icon="arrow-right">
                    {{ $currentAction['button'] }}
                </flux:button>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <p class="text-sm font-semibold text-zinc-900 dark:text-white">{{ __('Review progress') }}</p>
                <span class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">{{ $review->progressPercentage() }}%</span>
            </div>
            <flux:progress :value="$review->progressPercentage()" />
            <p class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                {{ __('Step :current of :total in the WFD PLS methodology.', ['current' => $review->current_step_number, 'total' => $review->steps->count()]) }}
            </p>
        </div>
    </section>

    <section>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="lg" level="2">{{ __('Workspace record') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('A single view of what is recorded in PLSAssist and the next useful action in each area.') }}</flux:text>
            </div>
            <flux:text class="text-xs">{{ __('Counts reflect records saved in this workspace.') }}</flux:text>
        </div>

        <div class="mt-4 divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            <div class="hidden grid-cols-[minmax(10rem,1fr)_5rem_8rem_minmax(0,1.1fr)_auto] gap-4 py-3 text-xs font-semibold uppercase tracking-wide text-zinc-500 lg:grid dark:text-zinc-400">
                <span>{{ __('Section') }}</span>
                <span>{{ __('Records') }}</span>
                <span>{{ __('Status') }}</span>
                <span>{{ __('What to do next') }}</span>
                <span></span>
            </div>

            @foreach ($workspaceRecord as $item)
                <div class="grid gap-2 py-4 lg:grid-cols-[minmax(10rem,1fr)_5rem_8rem_minmax(0,1.1fr)_auto] lg:items-center lg:gap-4">
                    <a href="{{ $item['route'] }}" wire:navigate class="min-w-0 text-sm font-semibold text-zinc-900 hover:text-violet-700 dark:text-white dark:hover:text-violet-300">
                        {{ $item['label'] }}
                    </a>
                    <span class="text-sm font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $item['value'] }}</span>
                    <div><flux:badge size="sm" :color="$item['status_color']">{{ $item['status'] }}</flux:badge></div>
                    <p class="text-sm leading-5 text-zinc-600 dark:text-zinc-400">{{ $item['detail'] }}</p>
                    <a href="{{ $item['route'] }}" wire:navigate class="text-sm font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">
                        {{ $item['action'] }}
                    </a>
                </div>
            @endforeach
        </div>
    </section>

    <section class="border-y border-zinc-200 py-6 dark:border-zinc-800">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <flux:heading size="lg" level="2">{{ __('Recent uploads') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('The latest legislation and evidence records attached to this review.') }}</flux:text>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <flux:button size="sm" variant="ghost" :href="route('pls.reviews.legislation', ['review' => $review])" wire:navigate icon="scale">
                    {{ __('Add legislation') }}
                </flux:button>
                <flux:button size="sm" variant="ghost" :href="route('pls.reviews.documents', ['review' => $review])" wire:navigate icon="arrow-up-tray">
                    {{ __('Add evidence') }}
                </flux:button>
            </div>
        </div>

        <div class="mt-4 divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
            @forelse ($recentUploads as $upload)
                <a href="{{ $upload['route'] }}" wire:navigate class="flex min-w-0 flex-col gap-1 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-5">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-zinc-900 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300">{{ $upload['title'] }}</p>
                        <p class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $upload['label'] }}@if ($upload['date'] !== '') · {{ $upload['date'] }}@endif</p>
                    </div>
                    <span class="shrink-0 text-xs font-medium text-zinc-600 dark:text-zinc-300">{{ $upload['status'] }}</span>
                </a>
            @empty
                <div class="py-4 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('No legislation or evidence has been uploaded yet.') }}
                </div>
            @endforelse
        </div>
    </section>

    <details class="group border-b border-zinc-200 pb-5 dark:border-zinc-800">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-semibold text-zinc-800 marker:hidden dark:text-zinc-200">
            <span>{{ __('PLS methodology reference') }}</span>
            <flux:icon icon="chevron-down" class="size-4 transition group-open:rotate-180" />
        </summary>
        <p class="mt-3 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-400">
            {{ __('These steps summarize WFD methodology. They are a reference for planning and checking the inquiry, while the navigation menu opens the relevant working areas.') }}
        </p>
        <ol class="mt-4 grid gap-x-6 gap-y-3 md:grid-cols-2">
            @foreach ($review->steps as $step)
                <li class="flex gap-3 text-sm">
                    <span class="inline-flex size-6 shrink-0 items-center justify-center rounded-full border border-zinc-200 text-xs font-semibold tabular-nums text-zinc-600 dark:border-zinc-700 dark:text-zinc-300">{{ $step->step_number }}</span>
                    <span class="leading-6 text-zinc-700 dark:text-zinc-300">{{ $step->title }}</span>
                </li>
            @endforeach
        </ol>
    </details>

    <flux:modal wire:model.self="showEditReviewModal" class="md:w-[38rem]">
        <form wire:submit="saveReviewDetails" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit review details') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Keep the working title, scope, and start date accurate as the inquiry takes shape.') }}</flux:text>
            </div>

            <flux:input wire:model="reviewTitle" :invalid="$errors->has('reviewTitle')" :label="__('Working title')" />

            <flux:textarea
                wire:model="reviewDescription"
                :invalid="$errors->has('reviewDescription')"
                :label="__('What is this review examining?')"
                rows="6"
                :placeholder="__('Describe the purpose, scope, and questions the inquiry will explore.')"
            />

            <flux:input wire:model="reviewStartDate" :invalid="$errors->has('reviewStartDate')" :label="__('Start date')" type="date" />

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>
                <flux:button variant="primary" type="submit">{{ __('Save details') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
