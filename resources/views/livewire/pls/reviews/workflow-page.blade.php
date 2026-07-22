<div class="space-y-6">
    <section id="review-details" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/50 sm:p-6">
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

        <div class="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1.35fr)_minmax(18rem,0.85fr)]">
            <div class="rounded-lg border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Purpose and scope') }}</p>
                @if (filled($review->description))
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-800 dark:text-zinc-200">{{ $review->description }}</p>
                @else
                    <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ __('No purpose or scope has been recorded yet.') }}</span>
                        @can('update', $review)
                            <button type="button" wire:click="prepareReviewEdit" class="font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">
                                {{ __('Add details') }}
                            </button>
                        @endcan
                    </div>
                @endif
            </div>

            <dl class="grid grid-cols-2 overflow-hidden rounded-lg border border-zinc-200 text-sm dark:border-zinc-800">
                <div class="min-w-0 border-b border-e border-zinc-200 p-4 dark:border-zinc-800">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Inquiry lead') }}</dt>
                    <dd class="mt-1 break-words font-medium text-zinc-900 dark:text-white">{{ $review->assignmentLabel() }}</dd>
                </div>
                <div class="min-w-0 border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Legislature') }}</dt>
                    <dd class="mt-1 break-words font-medium text-zinc-900 dark:text-white">{{ $review->legislature?->name ?? __('Not recorded') }}</dd>
                </div>
                <div class="min-w-0 border-e border-zinc-200 p-4 dark:border-zinc-800">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Location') }}</dt>
                    <dd class="mt-1 break-words font-medium text-zinc-900 dark:text-white">
                        {{ $review->assignmentLocationParts() !== [] ? implode(' - ', $review->assignmentLocationParts()) : __('Not recorded') }}
                    </dd>
                </div>
                <div class="min-w-0 p-4">
                    <dt class="text-xs font-medium text-zinc-500 dark:text-zinc-400">{{ __('Started') }}</dt>
                    <dd class="mt-1 font-medium text-zinc-900 dark:text-white">{{ $review->start_date?->format('j M Y') ?? __('Not recorded') }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="grid gap-6 rounded-lg border border-violet-200 bg-violet-50/50 p-5 shadow-sm dark:border-violet-500/25 dark:bg-violet-500/5 sm:p-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(17rem,0.75fr)]">
        <div class="border-b border-violet-200 pb-6 dark:border-violet-500/25 xl:border-b-0 xl:border-e xl:pe-6">
            <div class="flex items-center gap-3">
                <span class="inline-flex size-9 shrink-0 items-center justify-center rounded-lg bg-violet-700 text-sm font-semibold text-white shadow-sm dark:bg-violet-500">
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

        <div class="self-center rounded-lg border border-violet-200/80 bg-white/75 p-4 dark:border-violet-500/25 dark:bg-zinc-950/40">
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

        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($workspaceRecord as $item)
                <a href="{{ $item['route'] }}" wire:navigate class="group flex min-h-44 flex-col rounded-lg border border-zinc-200 bg-white p-4 shadow-sm transition hover:border-violet-300 hover:shadow dark:border-zinc-800 dark:bg-zinc-950/50 dark:hover:border-violet-500/50">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-zinc-950 transition group-hover:text-violet-800 dark:text-white dark:group-hover:text-violet-200">{{ $item['label'] }}</p>
                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ trans_choice('{0} No records|{1} :count record|[2,*] :count records', (int) $item['value'], ['count' => $item['value']]) }}</p>
                        </div>
                        <flux:badge size="sm" :color="$item['status_color']">{{ $item['status'] }}</flux:badge>
                    </div>
                    <p class="mt-4 text-sm leading-5 text-zinc-600 dark:text-zinc-400">{{ $item['detail'] }}</p>
                    <span class="mt-auto pt-4 text-sm font-medium text-violet-700 group-hover:text-violet-900 dark:text-violet-300 dark:group-hover:text-violet-100">{{ $item['action'] }}</span>
                </a>
            @endforeach
        </div>
    </section>

    <section>
        <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <flux:heading size="lg" level="2">{{ __('Ready to move forward?') }}</flux:heading>
                <flux:text class="mt-1 text-sm">{{ __('These checks reflect what is saved in PLSAssist. They support team judgment; they do not replace it.') }}</flux:text>
            </div>
            <span class="text-sm font-medium text-zinc-700 dark:text-zinc-200">
                {{ trans_choice('{0} no checks complete|{1} :count check complete|[2,*] :count checks complete', collect($readinessChecks)->where('ready', true)->count(), ['count' => collect($readinessChecks)->where('ready', true)->count()]) }}
            </span>
        </div>

        <div class="mt-4 grid gap-3 lg:grid-cols-2">
            @foreach ($readinessChecks as $check)
                <div class="flex items-start gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/50">
                    <span @class([
                        'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full',
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $check['ready'],
                        'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => ! $check['ready'],
                    ])>
                        <flux:icon :icon="$check['ready'] ? 'check' : 'minus'" class="size-3" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $check['label'] }}</p>
                        <p class="mt-0.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $check['detail'] }}</p>
                    </div>
                    @if ($check['route'] === '#review-details')
                        @can('update', $review)
                            <button type="button" wire:click="prepareReviewEdit" class="shrink-0 text-xs font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">{{ $check['action'] }}</button>
                        @endcan
                    @else
                        <a href="{{ $check['route'] }}" wire:navigate class="shrink-0 text-xs font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">{{ $check['action'] }}</a>
                    @endif
                </div>
            @endforeach
        </div>
    </section>

    <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/50 sm:p-6">
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

    <details class="group rounded-lg border border-zinc-200 bg-white px-5 py-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950/50 sm:px-6">
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
