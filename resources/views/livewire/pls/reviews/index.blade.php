<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-2">
            <flux:heading size="xl" level="1">{{ __('PLS Reviews') }}</flux:heading>
            <flux:subheading size="lg" class="max-w-3xl">
                {{ __('Track post-legislative scrutiny reviews, workflow progress, and the evidence attached to each inquiry.') }}
            </flux:subheading>
        </div>

        <div class="flex items-center gap-2">
            <flux:tabs variant="segmented" size="sm" wire:model.live="viewMode">
                <flux:tab name="cards" icon="squares-2x2" />
                <flux:tab name="table" icon="list-bullet" />
            </flux:tabs>
            <flux:button variant="primary" icon="plus" :href="route('pls.reviews.create')" wire:navigate>
                {{ __('Create review') }}
            </flux:button>
        </div>
    </div>

    @if ($reviews->isEmpty())
        <flux:card class="space-y-4">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('No reviews yet') }}</flux:heading>
                <flux:text class="max-w-2xl">
                    {{ __('Start the first PLS review to seed the 11-step workflow and begin collecting legislation, documents, findings, and recommendations.') }}
                </flux:text>
            </div>

            <div>
                <flux:button variant="primary" icon="plus" :href="route('pls.reviews.create')" wire:navigate>
                    {{ __('Create the first review') }}
                </flux:button>
            </div>
        </flux:card>
    @elseif ($viewMode === 'cards')
        <div class="grid gap-4 xl:grid-cols-2">
            @foreach ($reviews as $review)
                @php
                    $reviewAssignmentLabel = $review->committee?->name
                        ?? $review->legislature?->name
                        ?? $review->jurisdiction?->name
                        ?? __('Unassigned');
                @endphp

                <flux:card class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0 space-y-1">
                            <flux:link :href="route('pls.reviews.show', ['review' => $review->id])" wire:navigate variant="subtle" class="text-base font-semibold">
                                {{ $review->title }}
                            </flux:link>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $reviewAssignmentLabel }} · {{ $review->jurisdiction?->name ?? __('No jurisdiction') }}{{ $review->country?->name ? ', '.$review->country->name : '' }}
                            </flux:text>
                        </div>
                        <flux:badge size="sm">{{ $review->statusLabel() }}</flux:badge>
                    </div>

                    <div class="flex items-center gap-3 text-sm">
                        <span class="font-medium text-zinc-700 dark:text-zinc-300">{{ $review->currentStepTitle() }}</span>
                        <flux:text class="text-zinc-400 dark:text-zinc-500">
                            {{ __('Step :current of :total', ['current' => $review->current_step_number, 'total' => $review->steps->count()]) }}
                        </flux:text>
                    </div>

                    <div class="flex items-center gap-2">
                        <flux:progress :value="$review->progressPercentage()" class="flex-1" />
                        <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $review->progressPercentage() }}%</span>
                    </div>

                    <div class="flex items-center gap-4 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ trans_choice('{0} 0 legislation|{1} 1 legislation|[2,*] :count legislation', $review->legislation_count, ['count' => $review->legislation_count]) }}</span>
                        <span>{{ trans_choice('{0} 0 documents|{1} 1 document|[2,*] :count documents', $review->documents_count, ['count' => $review->documents_count]) }}</span>
                        <span>{{ trans_choice('{0} 0 findings|{1} 1 finding|[2,*] :count findings', $review->findings_count, ['count' => $review->findings_count]) }}</span>
                        <span>{{ trans_choice('{0} 0 recommendations|{1} 1 recommendation|[2,*] :count recommendations', $review->recommendations_count, ['count' => $review->recommendations_count]) }}</span>
                    </div>
                </flux:card>
            @endforeach
        </div>
    @else
        <flux:card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Review') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column>{{ __('Current step') }}</flux:table.column>
                    <flux:table.column>{{ __('Progress') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($reviews as $review)
                        @php
                            $reviewAssignmentLabel = $review->committee?->name
                                ?? $review->legislature?->name
                                ?? $review->jurisdiction?->name
                                ?? __('Unassigned');
                        @endphp

                        <flux:table.row :key="$review->id">
                            <flux:table.cell variant="strong">
                                <div class="min-w-0">
                                    <flux:link :href="route('pls.reviews.show', ['review' => $review->id])" wire:navigate variant="subtle" class="text-sm font-medium">
                                        {{ $review->title }}
                                    </flux:link>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $reviewAssignmentLabel }}
                                    </flux:text>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ $review->statusLabel() }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="min-w-0">
                                    <p class="text-sm text-zinc-700 dark:text-zinc-300">{{ $review->currentStepTitle() }}</p>
                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">
                                        {{ __('Step :current of :total', ['current' => $review->current_step_number, 'total' => $review->steps->count()]) }}
                                    </flux:text>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex items-center gap-2">
                                    <div class="h-1.5 w-16 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800">
                                        <div class="h-full rounded-full bg-zinc-900 dark:bg-zinc-300" style="width: {{ $review->progressPercentage() }}%;"></div>
                                    </div>
                                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $review->progressPercentage() }}%</span>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:button variant="ghost" size="sm" icon="arrow-right" :href="route('pls.reviews.show', ['review' => $review->id])" wire:navigate />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif

    @if ($reviews->isNotEmpty())
        <flux:pagination :paginator="$reviews" scroll-to="body" class="border-zinc-300" />
    @endif
</div>
