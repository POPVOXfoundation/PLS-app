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
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Findings & recommendations') }}</flux:heading>
            <div class="flex gap-2">
                <flux:modal.trigger name="add-finding">
                    <flux:button variant="ghost" size="sm" icon="plus">{{ __('Finding') }}</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="add-recommendation">
                    <flux:button variant="primary" size="sm" icon="plus">{{ __('Recommendation') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Findings') }}</flux:text>
                <p class="mt-auto pt-3 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->findings->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Recommendations') }}</flux:text>
                <p class="mt-auto pt-3 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->recommendations->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Gov. responses') }}</flux:text>
                <p class="mt-auto pt-3 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->governmentResponses->count() }}</p>
            </div>
        </div>

        @if ($review->findings->isEmpty())
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No findings or recommendations recorded yet.') }}
            </flux:text>
        @else
            <div class="space-y-4">
                @foreach ($review->findings as $finding)
                    <flux:card class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <flux:heading size="base">{{ $finding->title }}</flux:heading>
                                @if ($finding->summary)
                                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $finding->summary }}</flux:text>
                                @endif
                            </div>

                            <div class="flex shrink-0 gap-1">
                                <flux:modal.trigger name="edit-finding">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingFinding({{ $finding->id }})" />
                                </flux:modal.trigger>

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

                        @php $recommendations = $review->recommendations->where('finding_id', $finding->id); @endphp

                        @if ($recommendations->isNotEmpty())
                            <div class="divide-y divide-zinc-100 border-t border-zinc-100 pt-3 dark:divide-zinc-800/60 dark:border-zinc-800/60">
                                @foreach ($recommendations as $recommendation)
                                    <div class="flex items-start justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $recommendation->title }}</span>
                                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($recommendation->recommendation_type->value) }}</flux:badge>
                                            </div>
                                            @if ($recommendation->description)
                                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $recommendation->description }}</flux:text>
                                            @endif
                                        </div>
                                        <div class="flex shrink-0 gap-1">
                                            <flux:modal.trigger name="edit-recommendation">
                                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingRecommendation({{ $recommendation->id }})" />
                                            </flux:modal.trigger>
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
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:modal name="add-finding" class="md:w-[32rem]">
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

    <flux:modal name="edit-finding" class="md:w-[32rem]">
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

    <flux:modal name="add-recommendation" class="md:w-[32rem]">
        <form wire:submit="storeRecommendation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add recommendation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Tie each recommendation to an existing finding.') }}</flux:text>
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
                <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-recommendation" class="md:w-[32rem]">
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
                        x-text="`${@js(__('This will permanently remove'))} “${deleteConfirmation.title}” ${@js(__('from the review workspace.'))}`"
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
