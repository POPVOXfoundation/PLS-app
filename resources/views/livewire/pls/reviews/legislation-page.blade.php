<div>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Legislation') }}</flux:heading>
            <div class="flex gap-2">
                <flux:modal.trigger name="attach-legislation">
                    <flux:button variant="ghost" size="sm" icon="link" :disabled="$attachableLegislation->isEmpty()">{{ __('Attach') }}</flux:button>
                </flux:modal.trigger>
                <flux:modal.trigger name="create-legislation">
                    <flux:button variant="primary" size="sm" icon="plus">{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            </div>
        </div>

        @if ($review->legislation->isEmpty())
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No legislation linked to this review yet.') }}
            </flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Relationship') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Date enacted') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($review->legislation as $legislation)
                        <flux:table.row :key="$legislation->id">
                            <flux:table.cell variant="strong">
                                {{ $legislation->title }}
                                @if ($legislation->short_title)
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $legislation->short_title }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline((string) $legislation->pivot->relationship_type) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ \Illuminate\Support\Str::headline($legislation->legislation_type->value ?? '') }}</flux:table.cell>
                            <flux:table.cell>{{ $legislation->date_enacted?->toFormattedDateString() ?? '—' }}</flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal name="attach-legislation" class="md:w-96">
        <form wire:submit="attachLegislation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Attach legislation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Link existing legislation from this jurisdiction.') }}</flux:text>
            </div>

            @if ($attachableLegislation->isEmpty())
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('No additional legislation available for this jurisdiction.') }}
                </flux:text>
            @else
                <flux:select wire:model="attachLegislationId" :invalid="$errors->has('attachLegislationId')" :label="__('Legislation')" :placeholder="__('Select legislation')">
                    @foreach ($attachableLegislation as $attachable)
                        <flux:select.option :value="$attachable->id">{{ $attachable->title }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="attachLegislationRelationshipType" :invalid="$errors->has('attachLegislationRelationshipType')" :label="__('Relationship')">
                    @foreach ($legislationRelationshipTypes as $relationshipType)
                        <flux:select.option :value="$relationshipType->value">{{ \Illuminate\Support\Str::headline($relationshipType->value) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ __('Attach') }}</flux:button>
                </div>
            @endif
        </form>
    </flux:modal>

    <flux:modal name="create-legislation" class="md:w-[32rem]">
        <form wire:submit="createLegislation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Create legislation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create a new record and attach it to this review.') }}</flux:text>
            </div>

            <flux:input wire:model="newLegislationTitle" :invalid="$errors->has('newLegislationTitle')" :label="__('Title')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="newLegislationShortTitle" :invalid="$errors->has('newLegislationShortTitle')" :label="__('Short title')" />
                <flux:select wire:model="newLegislationType" :invalid="$errors->has('newLegislationType')" :label="__('Type')">
                    @foreach ($legislationTypes as $legislationType)
                        <flux:select.option :value="$legislationType->value">{{ \Illuminate\Support\Str::headline($legislationType->value) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="newLegislationDateEnacted" :invalid="$errors->has('newLegislationDateEnacted')" :label="__('Date enacted')" type="date" />
                <flux:select wire:model="newLegislationRelationshipType" :invalid="$errors->has('newLegislationRelationshipType')" :label="__('Relationship')">
                    @foreach ($legislationRelationshipTypes as $relationshipType)
                        <flux:select.option :value="$relationshipType->value">{{ \Illuminate\Support\Str::headline($relationshipType->value) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="newLegislationSummary" :invalid="$errors->has('newLegislationSummary')" :label="__('Summary')" rows="3" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
