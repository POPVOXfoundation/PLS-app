<div
    x-data="{
        deleteConfirmation: { type: '', id: null, title: '', noun: '' },
        setDeleteConfirmation(type, id, title, noun) {
            this.deleteConfirmation = { type, id, title, noun };
        },
        resetDeleteConfirmation() {
            this.deleteConfirmation = { type: '', id: null, title: '', noun: '' };
        }
    }"
>
    <flux:card class="space-y-6">
        @if ($review->findings->isEmpty())
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Findings & recommendations') }}</flux:heading>
                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareFindingCreate">{{ __('Add finding') }}</flux:button>
            </div>

            <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:heading size="base">{{ __('No findings recorded yet') }}</flux:heading>
                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('Start the analysis workspace by recording the first finding drawn from the review evidence.') }}
                </flux:text>
                <div class="mt-4">
                    <flux:button variant="primary" icon="plus" wire:click="prepareFindingCreate">{{ __('Add the first finding') }}</flux:button>
                </div>
            </div>
        @else
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Findings & recommendations') }}</flux:heading>
                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareFindingCreate">{{ __('Add finding') }}</flux:button>
            </div>

            <div class="space-y-4">
                @foreach ($review->findings as $finding)
                    @php $recommendations = $review->recommendations->where('finding_id', $finding->id); @endphp

                    <div wire:key="finding-{{ $finding->id }}" class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-800">
                        <div class="flex items-center justify-between gap-3 bg-zinc-50/70 px-4 py-3 dark:bg-zinc-900/50">
                            <div class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-zinc-900 dark:text-white">{{ $finding->title }}</span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($finding->finding_type->value) }}</flux:badge>
                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingFinding({{ $finding->id }})" />

                                <flux:modal.trigger name="confirm-analysis-delete">
                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="trash"
                                        :loading="false"
                                        x-on:click="setDeleteConfirmation('finding', {{ $finding->id }}, @js($finding->title), @js(__('finding')))"
                                    />
                                </flux:modal.trigger>
                            </div>
                        </div>

                        <div class="space-y-4 px-4 py-4">

                            @if ($finding->summary)
                                @php
                                    $findingSummaryPreview = \Illuminate\Support\Str::limit($finding->summary, 180);
                                    $findingSummaryIsTruncated = $findingSummaryPreview !== $finding->summary;
                                @endphp

                                <div x-data="{ expanded: false }" class="space-y-2">
                                    <flux:text x-show="! expanded" class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ $findingSummaryPreview }}
                                    </flux:text>

                                    @if ($findingSummaryIsTruncated)
                                        <flux:text x-show="expanded" x-cloak class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $finding->summary }}
                                        </flux:text>

                                        <button
                                            type="button"
                                            x-on:click="expanded = ! expanded"
                                            class="cursor-pointer text-xs font-medium text-sky-700 transition hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                                        >
                                            <span x-show="! expanded">{{ __('Show more') }}</span>
                                            <span x-show="expanded" x-cloak>{{ __('Show less') }}</span>
                                        </button>
                                    @endif
                                </div>
                            @endif

                            <div class="ml-4 space-y-3 rounded-xl bg-zinc-100/80 px-4 py-4 dark:bg-zinc-900/60">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-2">
                                        <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">
                                            {{ __('Recommendations') }}
                                        </flux:text>

                                        @if ($recommendations->isNotEmpty())
                                            <flux:badge size="sm">{{ $recommendations->count() }}</flux:badge>
                                        @endif
                                    </div>

                                    <button
                                        type="button"
                                        wire:click="prepareRecommendationCreate({{ $finding->id }})"
                                        class="inline-flex cursor-pointer items-center gap-2 text-sm font-medium text-sky-700 transition hover:text-sky-800 data-loading:opacity-50 data-loading:pointer-events-none dark:text-sky-300 dark:hover:text-sky-200"
                                    >
                                        <flux:icon icon="plus" class="size-4" />
                                        <span>{{ __('Add') }}</span>
                                    </button>
                                </div>

                                @if ($recommendations->isEmpty())
                                    <flux:text class="text-sm text-zinc-400 dark:text-zinc-500">{{ __('No recommendations yet') }}</flux:text>
                                @else
                                    <div class="space-y-3">
                                        @foreach ($recommendations as $recommendation)
                                            <div class="space-y-2 rounded-lg bg-white px-3 py-3 shadow-sm dark:bg-zinc-950/50">
                                                <div class="grid grid-cols-[minmax(0,1fr)_auto] items-start gap-3">
                                                    <div class="min-w-0 space-y-2">
                                                        <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $recommendation->title }}</span>

                                                        @if ($recommendation->description)
                                                            @php
                                                                $recommendationDescriptionPreview = \Illuminate\Support\Str::limit($recommendation->description, 140);
                                                                $recommendationDescriptionIsTruncated = $recommendationDescriptionPreview !== $recommendation->description;
                                                            @endphp

                                                            <div x-data="{ expanded: false }" class="space-y-2">
                                                                <flux:text x-show="! expanded" class="text-sm text-zinc-500 dark:text-zinc-400">
                                                                    {{ $recommendationDescriptionPreview }}
                                                                </flux:text>

                                                                @if ($recommendationDescriptionIsTruncated)
                                                                    <flux:text x-show="expanded" x-cloak class="text-sm text-zinc-500 dark:text-zinc-400">
                                                                        {{ $recommendation->description }}
                                                                    </flux:text>

                                                                    <button
                                                                        type="button"
                                                                        x-on:click="expanded = ! expanded"
                                                                        class="cursor-pointer text-xs font-medium text-sky-700 transition hover:text-sky-800 dark:text-sky-300 dark:hover:text-sky-200"
                                                                    >
                                                                        <span x-show="! expanded">{{ __('Show more') }}</span>
                                                                        <span x-show="expanded" x-cloak>{{ __('Show less') }}</span>
                                                                    </button>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>

                                                    <div class="flex shrink-0 flex-col items-end gap-2 pl-3">
                                                        <div class="flex items-center gap-1">
                                                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingRecommendation({{ $recommendation->id }})" />
                                                            <flux:modal.trigger name="confirm-analysis-delete">
                                                                <flux:button
                                                                    variant="ghost"
                                                                    size="sm"
                                                                    icon="trash"
                                                                    :loading="false"
                                                                    x-on:click="setDeleteConfirmation('recommendation', {{ $recommendation->id }}, @js($recommendation->title), @js(__('recommendation')))"
                                                                />
                                                            </flux:modal.trigger>
                                                        </div>

                                                        <flux:badge size="sm" class="whitespace-nowrap">{{ \Illuminate\Support\Str::headline($recommendation->recommendation_type->value) }}</flux:badge>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:modal wire:model.self="showAddFindingModal" class="md:w-[32rem]">
        <form wire:submit="storeFinding" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add finding') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Capture a core conclusion from the review evidence.') }}</flux:text>
            </div>

            <flux:input wire:model="findingTitle" :invalid="$errors->has('findingTitle')" :label="__('Title')" />

            <flux:select wire:model="findingType" :invalid="$errors->has('findingType')" :label="__('Type')">
                @foreach ($findingTypes as $type)
                    <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="findingSummary" :invalid="$errors->has('findingSummary')" :label="__('Summary')" rows="3" />
            <flux:textarea wire:model="findingDetail" :invalid="$errors->has('findingDetail')" :label="__('Detail')" rows="4" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add finding') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditFindingModal" class="md:w-[32rem]">
        <form wire:submit="updateFinding" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit finding') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Refine the wording, classification, or detail for this finding.') }}</flux:text>
            </div>

            <flux:input wire:model="findingTitle" :invalid="$errors->has('findingTitle')" :label="__('Title')" />

            <flux:select wire:model="findingType" :invalid="$errors->has('findingType')" :label="__('Type')">
                @foreach ($findingTypes as $type)
                    <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="findingSummary" :invalid="$errors->has('findingSummary')" :label="__('Summary')" rows="3" />
            <flux:textarea wire:model="findingDetail" :invalid="$errors->has('findingDetail')" :label="__('Detail')" rows="4" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="add-analysis-recommendation" class="md:w-[32rem]">
        <form wire:submit="storeRecommendation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add recommendation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Add a recommendation for this finding.') }}</flux:text>
            </div>

            <flux:input wire:model="recommendationTitle" :invalid="$errors->has('recommendationTitle')" :label="__('Title')" />

            <flux:select wire:model="recommendationType" :invalid="$errors->has('recommendationType')" :label="__('Type')">
                @foreach ($recommendationTypes as $type)
                    <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="recommendationDescription" :invalid="$errors->has('recommendationDescription')" :label="__('Description')" rows="4" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditRecommendationModal" class="md:w-[32rem]">
        <form wire:submit="updateRecommendation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit recommendation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Update the recommendation text or move it to a different finding if needed.') }}</flux:text>
            </div>

            <flux:select wire:model="recommendationFindingId" :invalid="$errors->has('recommendationFindingId')" :label="__('Finding')" :placeholder="__('Select finding')">
                @foreach ($review->findings as $findingOption)
                    <flux:select.option :value="$findingOption->id">{{ $findingOption->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:input wire:model="recommendationTitle" :invalid="$errors->has('recommendationTitle')" :label="__('Title')" />

            <flux:select wire:model="recommendationType" :invalid="$errors->has('recommendationType')" :label="__('Type')">
                @foreach ($recommendationTypes as $type)
                    <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea wire:model="recommendationDescription" :invalid="$errors->has('recommendationDescription')" :label="__('Description')" rows="4" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-analysis-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="`${@js(__('Delete this'))} ${deleteConfirmation.noun || @js(__('record'))}?`"></flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span
                        x-show="deleteConfirmation.title"
                        x-text="`${@js(__('This will permanently remove'))} &quot;${deleteConfirmation.title}&quot; ${@js(__('from the review workspace.'))}`"
                    ></span>
                    <span x-show="! deleteConfirmation.title">{{ __('This will permanently remove the selected item from the review workspace.') }}</span>
                </flux:text>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('This action cannot be undone.') }}
                </flux:text>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="filled" type="button" x-on:click="resetDeleteConfirmation()">
                        {{ __('Cancel') }}
                    </flux:button>
                </flux:modal.close>

                <flux:modal.close>
                    <flux:button
                        variant="danger"
                        type="button"
                        :loading="false"
                        x-on:click="$wire.confirmDeletion(deleteConfirmation.type, deleteConfirmation.id); resetDeleteConfirmation()"
                        x-bind:disabled="! deleteConfirmation.type || ! deleteConfirmation.id"
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
