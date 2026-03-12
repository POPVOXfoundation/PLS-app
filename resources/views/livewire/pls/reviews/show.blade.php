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
    class="flex h-full w-full flex-1 flex-col gap-6"
>
    @php
        $reviewAssignmentLabel = $review->committee?->name
            ?? $review->legislature?->name
            ?? $review->jurisdiction?->name
            ?? __('Unassigned');

            $reviewLocationParts = array_values(array_filter([
                $review->committee ? $review->legislature?->name : null,
                $review->jurisdiction?->name,
                $review->country?->name,
            ]));
    @endphp

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- ── Header ── --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $review->title }}</flux:heading>
                <flux:badge>{{ $review->statusLabel() }}</flux:badge>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $reviewAssignmentLabel }}@if ($reviewLocationParts !== []) · {{ implode(' · ', $reviewLocationParts) }}@endif
            </flux:text>

            @if ($review->description)
                <flux:text class="max-w-4xl">{{ $review->description }}</flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
                {{ __('All reviews') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" :href="route('pls.reviews.create')" wire:navigate>
                {{ __('New review') }}
            </flux:button>
        </div>
    </div>

    {{-- ── Progress summary ── --}}
    <flux:card class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Step :current of :total', ['current' => $review->current_step_number, 'total' => $review->steps->count()]) }}
                · {{ $review->currentStepTitle() }}
            </flux:text>
            <flux:text class="text-sm font-medium tabular-nums">{{ $review->progressPercentage() }}%</flux:text>
        </div>
        <flux:progress :value="$review->progressPercentage()" />
    </flux:card>

    @if ($workspaceGuidance)
        <flux:card class="space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm">{{ __('Current workspace focus') }}</flux:badge>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Best next area: :tab', ['tab' => $workspaceGuidance['tab']]) }}
                        </flux:text>
                    </div>

                    <div class="space-y-1">
                        <flux:heading size="lg">{{ $workspaceGuidance['title'] }}</flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $workspaceGuidance['summary'] }}
                        </flux:text>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-300 lg:max-w-sm">
                    <span class="block text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Do next') }}</span>
                    <span class="mt-2 block">{{ $workspaceGuidance['action'] }}</span>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- ── Tabbed content ── --}}
    <flux:tab.group class="[&_[data-flux-tab-panel]]:pt-4">
        <flux:tabs>
            <flux:tab name="workflow" icon="list-bullet">{{ __('Workflow') }}</flux:tab>
            <flux:tab name="legislation" icon="scale">{{ __('Legislation') }}</flux:tab>
            <flux:tab name="documents" icon="document-text">{{ __('Documents') }}</flux:tab>
            <flux:tab name="stakeholders" icon="users">{{ __('Stakeholders') }}</flux:tab>
            <flux:tab name="consultations" icon="chat-bubble-left-right">{{ __('Consultations') }}</flux:tab>
            <flux:tab name="analysis" icon="light-bulb">{{ __('Analysis') }}</flux:tab>
            <flux:tab name="reports" icon="clipboard-document-list">{{ __('Reports') }}</flux:tab>
        </flux:tabs>

        {{-- ════════════════════════════════════════════════
             TAB: Workflow
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="workflow">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                {{-- Step list --}}
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

                {{-- Selected step detail --}}
                @if ($selectedStep)
                    <flux:card class="space-y-4">
                        <flux:badge size="sm">{{ __('Step :number', ['number' => $selectedStep->step_number]) }}</flux:badge>

                        <div class="space-y-1">
                            <flux:heading size="lg">{{ $selectedStep->title }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stepContext($selectedStep) }}</flux:text>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ($this->stepMetricCards($review, $selectedStep) as $metric)
                                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</flux:text>
                                    <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $metric['value'] }}</p>
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
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Legislation
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="legislation">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Legislation') }}</flux:heading>
                    <div class="flex gap-2">
                        <flux:modal.trigger name="attach-legislation">
                            <flux:button variant="ghost" size="sm" icon="link">{{ __('Attach existing') }}</flux:button>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="create-legislation">
                            <flux:button variant="primary" size="sm" icon="plus">{{ __('Create new') }}</flux:button>
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

            {{-- Attach legislation modal --}}
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

            {{-- Create legislation modal --}}
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
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Documents
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="documents">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                    <flux:modal.trigger name="add-document">
                        <flux:button variant="primary" size="sm" icon="plus">{{ __('Add document') }}</flux:button>
                    </flux:modal.trigger>
                </div>

                @if ($review->documents->isEmpty())
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No documents linked to this review yet.') }}
                    </flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Title') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('MIME') }}</flux:table.column>
                            <flux:table.column>{{ __('Size') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($review->documents as $document)
                                <flux:table.row :key="$document->id">
                                    <flux:table.cell variant="strong">
                                        {{ $document->title }}
                                        @if ($document->summary)
                                            <flux:text class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($document->summary, 80) }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($document->document_type->value) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $document->mime_type }}</flux:table.cell>
                                    <flux:table.cell>{{ $document->fileSizeLabel() }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.trigger name="edit-document">
                                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingDocument({{ $document->id }})">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <flux:modal.trigger name="confirm-workspace-delete">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    :loading="false"
                                                    x-on:click="setDeleteConfirmation('document', {{ $document->id }}, @js($document->title), @js(__('document')))"
                                                >
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            {{-- Add document modal --}}
            <flux:modal name="add-document" class="md:w-[32rem]">
                <form wire:submit="storeDocument" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add document') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Upload the working file now or record an existing storage path and metadata.') }}</flux:text>
                    </div>

                    <flux:input wire:model="documentTitle" :invalid="$errors->has('documentTitle')" :label="__('Title')" />

                    <flux:field>
                        <flux:label>{{ __('Upload file') }}</flux:label>
                        <input
                            type="file"
                            wire:model="documentUpload"
                            class="block w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-200"
                        />
                        <flux:error name="documentUpload" />
                        <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Optional if the file already exists in storage and you want to reference its path directly.') }}
                        </flux:text>
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="documentType" :invalid="$errors->has('documentType')" :label="__('Type')">
                            @foreach ($documentTypes as $type)
                                <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="documentStoragePath" :invalid="$errors->has('documentStoragePath')" :label="__('Storage path')" placeholder="pls/reviews/12/documents/file.pdf" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="documentMimeType" :invalid="$errors->has('documentMimeType')" :label="__('MIME type')" />
                        <flux:input wire:model="documentFileSize" :invalid="$errors->has('documentFileSize')" :label="__('File size (bytes)')" type="number" min="1" />
                    </div>

                    <flux:textarea wire:model="documentSummary" :invalid="$errors->has('documentSummary')" :label="__('Summary')" rows="3" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-document" class="md:w-[32rem]">
                <form wire:submit="updateDocument" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit document') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Update document metadata or replace the stored file for this review record.') }}</flux:text>
                    </div>

                    <flux:input wire:model="documentTitle" :invalid="$errors->has('documentTitle')" :label="__('Title')" />

                    <flux:field>
                        <flux:label>{{ __('Replace file') }}</flux:label>
                        <input
                            type="file"
                            wire:model="documentUpload"
                            class="block w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-200"
                        />
                        <flux:error name="documentUpload" />
                        <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Leave blank to keep the current stored file and metadata path.') }}
                        </flux:text>
                    </flux:field>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="documentType" :invalid="$errors->has('documentType')" :label="__('Type')">
                            @foreach ($documentTypes as $type)
                                <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="documentStoragePath" :invalid="$errors->has('documentStoragePath')" :label="__('Storage path')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="documentMimeType" :invalid="$errors->has('documentMimeType')" :label="__('MIME type')" />
                        <flux:input wire:model="documentFileSize" :invalid="$errors->has('documentFileSize')" :label="__('File size (bytes)')" type="number" min="1" />
                    </div>

                    <flux:textarea wire:model="documentSummary" :invalid="$errors->has('documentSummary')" :label="__('Summary')" rows="3" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Stakeholders
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="stakeholders">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.25fr)_minmax(340px,0.9fr)]">
                <flux:card class="space-y-4">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <flux:heading size="lg">{{ __('Stakeholders') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Track who should be consulted and see which stakeholders have already submitted evidence.') }}
                            </flux:text>
                        </div>

                        <div class="flex items-center gap-2">
                            <flux:select wire:model.live="stakeholderTypeFilter" size="sm" class="min-w-40">
                                <flux:select.option value="all">{{ __('All types') }}</flux:select.option>
                                @foreach ($stakeholderTypes as $stakeholderTypeOption)
                                    <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:modal.trigger name="add-stakeholder">
                                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add stakeholder') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    @if ($review->stakeholders->isEmpty())
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('No stakeholders recorded yet.') }}
                        </flux:text>
                    @elseif ($filteredStakeholders->isEmpty())
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('No stakeholders match the current filter.') }}
                        </flux:text>
                    @else
                        <div class="space-y-3">
                            @foreach ($filteredStakeholders as $stakeholder)
                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0 space-y-1">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $stakeholder->name }}</p>
                                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($stakeholder->stakeholder_type->value) }}</flux:badge>
                                            </div>

                                            @if (($stakeholder->contact_details['organization'] ?? null) || ($stakeholder->contact_details['email'] ?? null) || ($stakeholder->contact_details['phone'] ?? null))
                                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                                    @if ($stakeholder->contact_details['organization'] ?? null)
                                                        <span>{{ $stakeholder->contact_details['organization'] }}</span>
                                                    @endif
                                                    @if ($stakeholder->contact_details['email'] ?? null)
                                                        <span>{{ $stakeholder->contact_details['email'] }}</span>
                                                    @endif
                                                    @if ($stakeholder->contact_details['phone'] ?? null)
                                                        <span>{{ $stakeholder->contact_details['phone'] }}</span>
                                                    @endif
                                                </div>
                                            @endif
                                        </div>

                                        <span class="shrink-0 text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                                            {{ trans_choice('{0} 0 submissions|{1} 1 submission|[2,*] :count submissions', $stakeholder->submissions->count(), ['count' => $stakeholder->submissions->count()]) }}
                                        </span>
                                    </div>

                                    @if ($stakeholder->submissions->isNotEmpty())
                                        <div class="mt-4 space-y-2 border-t border-zinc-100 pt-3 dark:border-zinc-800/60">
                                            <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                                {{ __('Linked submissions') }}
                                            </flux:text>

                                            @foreach ($stakeholder->submissions as $submission)
                                                <div class="rounded-lg bg-zinc-50 px-3 py-2 dark:bg-zinc-900/60">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <flux:text class="text-sm text-zinc-700 dark:text-zinc-300">
                                                                {{ $submission->summary }}
                                                            </flux:text>
                                                            <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                                <span>{{ $submission->submitted_at?->toFormattedDateString() ?? __('Undated') }}</span>
                                                                @if ($submission->document)
                                                                    <span>{{ $submission->document->title }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>

                <flux:card class="space-y-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="space-y-1">
                            <flux:heading size="lg">{{ __('Implementing agencies') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keep the implementation-review step grounded in the institutions responsible for delivery and oversight.') }}
                            </flux:text>
                        </div>

                        <flux:modal.trigger name="add-implementing-agency">
                            <flux:button variant="primary" size="sm" icon="plus">{{ __('Add agency') }}</flux:button>
                        </flux:modal.trigger>
                    </div>

                    @if ($review->implementingAgencies->isEmpty())
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('No implementing agencies recorded yet.') }}
                        </flux:text>
                    @else
                        <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                            @foreach ($review->implementingAgencies as $agency)
                                <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $agency->name }}</p>
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ \Illuminate\Support\Str::headline($agency->agency_type->value) }}
                                        </flux:text>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>
            </div>

            <flux:modal name="add-stakeholder" class="md:w-[34rem]">
                <form wire:submit="storeStakeholder" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add stakeholder') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Capture the organizations, experts, and public-interest actors who should inform this review.') }}</flux:text>
                    </div>

                    <flux:input wire:model="stakeholderName" :invalid="$errors->has('stakeholderName')" :label="__('Name')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="stakeholderType" :invalid="$errors->has('stakeholderType')" :label="__('Type')">
                            @foreach ($stakeholderTypes as $stakeholderTypeOption)
                                <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="stakeholderOrganization" :invalid="$errors->has('stakeholderOrganization')" :label="__('Organization')" />
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="stakeholderEmail" :invalid="$errors->has('stakeholderEmail')" :label="__('Email')" type="email" />
                        <flux:input wire:model="stakeholderPhone" :invalid="$errors->has('stakeholderPhone')" :label="__('Phone')" />
                    </div>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add stakeholder') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-implementing-agency" class="md:w-[30rem]">
                <form wire:submit="storeImplementingAgency" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add implementing agency') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Record the ministries, departments, agencies, and regulators accountable for implementation.') }}</flux:text>
                    </div>

                    <flux:input wire:model="implementingAgencyName" :invalid="$errors->has('implementingAgencyName')" :label="__('Agency name')" />

                    <flux:select wire:model="implementingAgencyType" :invalid="$errors->has('implementingAgencyType')" :label="__('Agency type')">
                        @foreach ($implementingAgencyTypes as $agencyTypeOption)
                            <flux:select.option :value="$agencyTypeOption->value">{{ \Illuminate\Support\Str::headline($agencyTypeOption->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add agency') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Consultations
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="consultations">
            @php
                $completedConsultations = $review->consultations
                    ->filter(fn ($consultation) => $consultation->held_at !== null)
                    ->sortByDesc('held_at');
                $plannedConsultations = $review->consultations
                    ->filter(fn ($consultation) => $consultation->held_at === null)
                    ->sortBy('title');
            @endphp

            <div class="space-y-6">
                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Consultations held') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $completedConsultations->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Planned consultations') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $plannedConsultations->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Submissions received') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->submissions->count() }}</p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.95fr)]">
                    <flux:card class="space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Consultation activity') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Distinguish planned engagement from completed hearings, roundtables, interviews, and public consultations.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-consultation">
                                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add consultation') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        <div class="space-y-5">
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                        {{ __('Completed') }}
                                    </flux:text>
                                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $completedConsultations->count() }}</span>
                                </div>

                                @if ($completedConsultations->isEmpty())
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No completed consultation activity recorded yet.') }}
                                    </flux:text>
                                @else
                                    <div class="space-y-3">
                                        @foreach ($completedConsultations as $consultation)
                                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 space-y-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $consultation->title }}</p>
                                                            <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($consultation->consultation_type->value) }}</flux:badge>
                                                        </div>
                                                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                            <span>{{ $consultation->held_at?->toFormattedDateString() }}</span>
                                                            @if ($consultation->document)
                                                                <span>{{ $consultation->document->title }}</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <flux:modal.trigger name="edit-consultation">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingConsultation({{ $consultation->id }})">
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>

                                                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $consultation->summary }}</flux:text>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800/60">
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                        {{ __('Planned') }}
                                    </flux:text>
                                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $plannedConsultations->count() }}</span>
                                </div>

                                @if ($plannedConsultations->isEmpty())
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No planned consultation work queued yet.') }}
                                    </flux:text>
                                @else
                                    <div class="space-y-3">
                                        @foreach ($plannedConsultations as $consultation)
                                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 space-y-1">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $consultation->title }}</p>
                                                            <flux:badge size="sm" color="zinc">{{ \Illuminate\Support\Str::headline($consultation->consultation_type->value) }}</flux:badge>
                                                        </div>
                                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                                            {{ __('Planned engagement activity') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:modal.trigger name="edit-consultation">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingConsultation({{ $consultation->id }})">
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>

                                                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $consultation->summary }}</flux:text>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </flux:card>

                    <flux:card class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Submissions') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Log written evidence and connect each submission to a stakeholder and supporting document.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-submission">
                                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add submission') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($review->submissions->isEmpty())
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No submissions logged yet.') }}
                            </flux:text>
                        @else
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                @foreach ($review->submissions->sortByDesc('submitted_at') as $submission)
                                    <div class="space-y-2 py-3 first:pt-0 last:pb-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $submission->stakeholder?->name ?? __('Unknown stakeholder') }}</p>
                                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    <span>{{ $submission->submitted_at?->toFormattedDateString() ?? __('Undated') }}</span>
                                                    @if ($submission->document)
                                                        <span>{{ $submission->document->title }}</span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $submission->summary }}</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                </div>
            </div>

            <flux:modal name="add-consultation" class="md:w-[34rem]">
                <form wire:submit="storeConsultation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add consultation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Record a planned or completed consultation activity for this review.') }}</flux:text>
                    </div>

                    <flux:input wire:model="consultationTitle" :invalid="$errors->has('consultationTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="consultationType" :invalid="$errors->has('consultationType')" :label="__('Type')">
                            @foreach ($consultationTypes as $consultationTypeOption)
                                <flux:select.option :value="$consultationTypeOption->value">{{ \Illuminate\Support\Str::headline($consultationTypeOption->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="consultationHeldAt" :invalid="$errors->has('consultationHeldAt')" :label="__('Date held')" type="date" />
                    </div>

                    <flux:select wire:model="consultationDocumentId" :invalid="$errors->has('consultationDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                        @foreach ($review->documents as $documentOption)
                            <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="consultationSummary" :invalid="$errors->has('consultationSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add consultation') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-consultation" class="md:w-[34rem]">
                <form wire:submit="updateConsultation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit consultation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Update the schedule, outcome summary, or linked materials for this consultation.') }}</flux:text>
                    </div>

                    <flux:input wire:model="consultationTitle" :invalid="$errors->has('consultationTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="consultationType" :invalid="$errors->has('consultationType')" :label="__('Type')">
                            @foreach ($consultationTypes as $consultationTypeOption)
                                <flux:select.option :value="$consultationTypeOption->value">{{ \Illuminate\Support\Str::headline($consultationTypeOption->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="consultationHeldAt" :invalid="$errors->has('consultationHeldAt')" :label="__('Date held')" type="date" />
                    </div>

                    <flux:select wire:model="consultationDocumentId" :invalid="$errors->has('consultationDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                        @foreach ($review->documents as $documentOption)
                            <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="consultationSummary" :invalid="$errors->has('consultationSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-submission" class="md:w-[34rem]">
                <form wire:submit="storeSubmission" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add submission') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Log written evidence and connect it to the stakeholder record that submitted it.') }}</flux:text>
                    </div>

                    <flux:select wire:model="submissionStakeholderId" :invalid="$errors->has('submissionStakeholderId')" :label="__('Stakeholder')" :placeholder="__('Select stakeholder')">
                        @foreach ($review->stakeholders as $stakeholderOption)
                            <flux:select.option :value="$stakeholderOption->id">{{ $stakeholderOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="submissionSubmittedAt" :invalid="$errors->has('submissionSubmittedAt')" :label="__('Submitted at')" type="date" />
                        <flux:select wire:model="submissionDocumentId" :invalid="$errors->has('submissionDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($review->documents as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:textarea wire:model="submissionSummary" :invalid="$errors->has('submissionSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add submission') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Analysis (Findings + Recommendations)
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="analysis">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Findings & recommendations') }}</flux:heading>
                    <div class="flex gap-2">
                        <flux:modal.trigger name="add-finding">
                            <flux:button variant="ghost" size="sm" icon="plus">{{ __('Add finding') }}</flux:button>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="add-recommendation">
                            <flux:button variant="primary" size="sm" icon="plus">{{ __('Add recommendation') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Findings') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->findings->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Recommendations') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->recommendations->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Gov. responses') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->governmentResponses->count() }}</p>
                    </div>
                </div>

                @if ($review->findings->isEmpty())
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No findings or recommendations recorded yet.') }}
                    </flux:text>
                @else
                    <div class="space-y-3">
                        @foreach ($review->findings as $finding)
                            <flux:card class="space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $finding->title }}</span>
                                        <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($finding->finding_type->value) }}</flux:badge>
                                    </div>

                                    <div class="flex shrink-0 gap-2">
                                        <flux:modal.trigger name="edit-finding">
                                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingFinding({{ $finding->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                        </flux:modal.trigger>

                                        <flux:modal.trigger name="confirm-workspace-delete">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                :loading="false"
                                                x-on:click="setDeleteConfirmation('finding', {{ $finding->id }}, @js($finding->title), @js(__('finding')))"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </div>

                                @if ($finding->summary)
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $finding->summary }}</flux:text>
                                @endif

                                @php $recommendations = $review->recommendations->where('finding_id', $finding->id); @endphp

                                @if ($recommendations->isNotEmpty())
                                    <div class="divide-y divide-zinc-100 border-t border-zinc-100 pt-2 dark:divide-zinc-800/60 dark:border-zinc-800/60">
                                        @foreach ($recommendations as $recommendation)
                                            <div class="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                                <div class="min-w-0">
                                                    <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $recommendation->title }}</span>
                                                    @if ($recommendation->description)
                                                        <flux:text class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $recommendation->description }}</flux:text>
                                                    @endif
                                                </div>
                                                <div class="flex shrink-0 items-center gap-2">
                                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($recommendation->recommendation_type->value) }}</flux:badge>
                                                    <flux:modal.trigger name="edit-recommendation">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingRecommendation({{ $recommendation->id }})">
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                    <flux:modal.trigger name="confirm-workspace-delete">
                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="trash"
                                                            :loading="false"
                                                            x-on:click="setDeleteConfirmation('recommendation', {{ $recommendation->id }}, @js($recommendation->title), @js(__('recommendation')))"
                                                        >
                                                            {{ __('Delete') }}
                                                        </flux:button>
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

            {{-- Add finding modal --}}
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

            {{-- Add recommendation modal --}}
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
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Reports
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="reports">
            @php
                $reportWorkflowFocus = $this->reportWorkflowFocus($review);
                $publishedFinalReports = $review->reports->filter(
                    fn ($report) => $report->report_type === \App\Domain\Reporting\Enums\ReportType::FinalReport
                        && $report->status === \App\Domain\Reporting\Enums\ReportStatus::Published,
                );
            @endphp

            <div class="space-y-6">
                @if ($reportWorkflowFocus)
                    <flux:card class="space-y-3">
                        <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                            {{ $reportWorkflowFocus['label'] }}
                        </flux:text>

                        <div class="space-y-1">
                            <flux:heading size="lg">{{ $reportWorkflowFocus['title'] }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $reportWorkflowFocus['summary'] }}
                            </flux:text>
                        </div>
                    </flux:card>
                @endif

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Reports') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->reports->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Final reports') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->reports->where('report_type', \App\Domain\Reporting\Enums\ReportType::FinalReport)->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Awaiting response') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $this->awaitingGovernmentResponseCount($review) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Overdue responses') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $this->overdueGovernmentResponseCount($review) }}</p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.95fr)]">
                    <flux:card class="space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Reports') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Track draft outputs, final publications, and whether government follow-up has been captured against each report.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-report">
                                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add report') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($review->reports->isEmpty())
                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No report records created yet.') }}
                                </flux:text>
                                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Start by creating the committee’s draft or final report record, then link the publication document when it is ready.') }}
                                </flux:text>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($review->reports->sortByDesc(fn ($report) => $report->published_at?->timestamp ?? $report->created_at?->timestamp ?? 0) as $report)
                                    @php
                                        $responseIndicator = $this->reportResponseIndicator($report);
                                        $latestResponse = $this->latestGovernmentResponseForReport($report);
                                    @endphp

                                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0 space-y-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $report->title }}</p>
                                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($report->report_type->value) }}</flux:badge>
                                                </div>

                                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    <span>{{ \Illuminate\Support\Str::headline($report->status->value) }}</span>
                                                    <span>{{ $report->published_at?->toFormattedDateString() ?? __('Not published') }}</span>
                                                    @if ($report->document)
                                                        <span>{{ $report->document->title }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex shrink-0 items-center gap-2">
                                                <span class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md border px-2.5 py-1 text-center text-[11px] font-medium leading-none {{ $responseIndicator['classes'] }}">
                                                    {{ $responseIndicator['label'] }}
                                                </span>

                                                <flux:modal.trigger name="edit-report">
                                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingReport({{ $report->id }})">
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:modal.trigger>

                                                <flux:modal.trigger name="confirm-workspace-delete">
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="trash"
                                                        :loading="false"
                                                        x-on:click="setDeleteConfirmation('report', {{ $report->id }}, @js($report->title), @js(__('report')))"
                                                    >
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </flux:modal.trigger>
                                            </div>
                                        </div>

                                        @if ($latestResponse)
                                            <div class="mt-4 grid gap-2 border-t border-zinc-100 pt-3 text-sm text-zinc-600 dark:border-zinc-800/60 dark:text-zinc-400 sm:grid-cols-3">
                                                <div>
                                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Latest response') }}</flux:text>
                                                    <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ \Illuminate\Support\Str::headline($latestResponse->response_status->value) }}</p>
                                                </div>
                                                <div>
                                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Received') }}</flux:text>
                                                    <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $latestResponse->received_at?->toFormattedDateString() ?? __('Not recorded') }}</p>
                                                </div>
                                                <div>
                                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Linked document') }}</flux:text>
                                                    <p class="mt-1 truncate text-sm text-zinc-800 dark:text-zinc-200">{{ $latestResponse->document?->title ?? __('No document linked') }}</p>
                                                </div>
                                            </div>

                                            @if ($latestResponse->summary)
                                                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ $latestResponse->summary }}
                                                </flux:text>
                                            @endif
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>

                    <flux:card class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Government responses') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Track response requests, overdue follow-up, and formal replies against the report record they address.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-government-response">
                                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add response') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($review->governmentResponses->isEmpty())
                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No government responses recorded yet.') }}
                                </flux:text>

                                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    @if ($publishedFinalReports->isEmpty())
                                        {{ __('Once a final report is published, record whether government has been asked to respond and capture any reply here.') }}
                                    @else
                                        {{ __('A final report has been published. Add a response record when government is asked to reply, when a response arrives, or when follow-up becomes overdue.') }}
                                    @endif
                                </flux:text>
                            </div>
                        @else
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                @foreach ($review->governmentResponses->sortByDesc(fn ($response) => $response->received_at?->timestamp ?? $response->created_at?->timestamp ?? 0) as $response)
                                    @php
                                        $responseClasses = match ($response->response_status) {
                                            \App\Domain\Reporting\Enums\GovernmentResponseStatus::Received => 'border-emerald-200/80 bg-emerald-50/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300',
                                            \App\Domain\Reporting\Enums\GovernmentResponseStatus::Overdue => 'border-rose-200/80 bg-rose-50/80 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-300',
                                            \App\Domain\Reporting\Enums\GovernmentResponseStatus::Requested => 'border-amber-200/80 bg-amber-50/70 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/15 dark:text-amber-300',
                                        };
                                    @endphp

                                    <div class="space-y-2 py-3 first:pt-0 last:pb-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $response->report?->title ?? __('Unknown report') }}</p>
                                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    <span>{{ $response->received_at?->toFormattedDateString() ?? __('No received date') }}</span>
                                                    @if ($response->document)
                                                        <span>{{ $response->document->title }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <span class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md border px-2.5 py-1 text-center text-[11px] font-medium leading-none {{ $responseClasses }}">
                                                {{ \Illuminate\Support\Str::headline($response->response_status->value) }}
                                            </span>
                                        </div>

                                        @if ($response->summary)
                                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $response->summary }}</flux:text>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                </div>
            </div>

            {{-- Add report modal --}}
            <flux:modal name="add-report" class="md:w-[32rem]">
                <form wire:submit="storeReport" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add report') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Create a report record and optionally link it to a document.') }}</flux:text>
                    </div>

                    <flux:input wire:model="reportTitle" :invalid="$errors->has('reportTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportType" :invalid="$errors->has('reportType')" :label="__('Type')">
                            @foreach ($reportTypes as $type)
                                <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="reportStatus" :invalid="$errors->has('reportStatus')" :label="__('Status')">
                            @foreach ($reportStatuses as $status)
                                <flux:select.option :value="$status->value">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportDocumentId" :invalid="$errors->has('reportDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($review->documents as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="reportPublishedAt" :invalid="$errors->has('reportPublishedAt')" :label="__('Published at')" type="date" />
                    </div>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-report" class="md:w-[32rem]">
                <form wire:submit="updateReport" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit report') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Keep the report record aligned with its publication status and linked document.') }}</flux:text>
                    </div>

                    <flux:input wire:model="reportTitle" :invalid="$errors->has('reportTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportType" :invalid="$errors->has('reportType')" :label="__('Type')">
                            @foreach ($reportTypes as $type)
                                <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="reportStatus" :invalid="$errors->has('reportStatus')" :label="__('Status')">
                            @foreach ($reportStatuses as $status)
                                <flux:select.option :value="$status->value">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportDocumentId" :invalid="$errors->has('reportDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($review->documents as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="reportPublishedAt" :invalid="$errors->has('reportPublishedAt')" :label="__('Published at')" type="date" />
                    </div>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-government-response" class="md:w-[34rem]">
                <form wire:submit="storeGovernmentResponse" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add government response') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Record the response status for a report and capture the document or summary that came back from government.') }}</flux:text>
                    </div>

                    <flux:select wire:model="governmentResponseReportId" :invalid="$errors->has('governmentResponseReportId')" :label="__('Report')" :placeholder="__('Select report')">
                        @foreach ($review->reports as $reportOption)
                            <flux:select.option :value="$reportOption->id">{{ $reportOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="governmentResponseStatus" :invalid="$errors->has('governmentResponseStatus')" :label="__('Response status')">
                            @foreach ($governmentResponseStatuses as $status)
                                <flux:select.option :value="$status->value">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="governmentResponseReceivedAt" :invalid="$errors->has('governmentResponseReceivedAt')" :label="__('Received at')" type="date" />
                    </div>

                    <flux:select wire:model="governmentResponseDocumentId" :invalid="$errors->has('governmentResponseDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                        @foreach ($review->documents as $documentOption)
                            <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="governmentResponseSummary" :invalid="$errors->has('governmentResponseSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add response') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:modal name="confirm-workspace-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
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
