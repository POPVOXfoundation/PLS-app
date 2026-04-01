<div
    x-data="{
        deleteConfirmation: { id: null, title: '', noun: '' },
        setDeleteConfirmation(id, title, noun) {
            this.deleteConfirmation = { id, title, noun };
        },
        resetDeleteConfirmation() {
            this.deleteConfirmation = { id: null, title: '', noun: '' };
        },
    }"
    class="space-y-6"
>
    <flux:card class="space-y-6">
        <div
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            class="space-y-5"
        >
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Legislation') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Add the source text and it will appear in the records table below.') }}
                </flux:text>
            </div>

            <div class="space-y-4">
                <flux:file-upload
                    wire:model="sourceUpload"
                    accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    :label="__('Source file')"
                >
                    <flux:file-upload.dropzone
                        :heading="__('Choose a file')"
                        :text="__('PDF or DOCX • :limit', ['limit' => $this->sourceUploadLimitLabel()])"
                    />
                </flux:file-upload>

                <flux:field>
                    <flux:error name="sourceUpload" />
                </flux:field>

                <flux:field x-cloak x-show="uploading" class="space-y-2">
                    <div class="flex items-center justify-between gap-3">
                        <flux:label>{{ __('Uploading') }}</flux:label>
                        <flux:text color="sky">
                            <span x-text="`${progress}%`"></span>
                        </flux:text>
                    </div>

                    <flux:progress value="0" color="sky" x-bind:value="progress" />

                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Moving the source into the review workspace.') }}
                    </flux:text>
                </flux:field>
            </div>

            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" wire:loading.flex wire:target="sourceUpload">
                <flux:icon icon="arrow-path" class="size-4 animate-spin text-sky-500" />
                <span>{{ __('Adding the source to records...') }}</span>
            </div>
        </div>
    </flux:card>

    <flux:card wire:poll.5s.keep-alive="refreshPendingAnalyses" class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Records') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Uploads stay here while they process, wait for review, or remain saved to the review.') }}
                </flux:text>
            </div>

            @if ($hasProcessingRecords)
                <div class="flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-sm text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                    <span>{{ __('Processing') }}</span>
                </div>
            @endif
        </div>

        @if ($recordRows === [])
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No records saved for this review yet.') }}
            </flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Record') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column align="end" class="w-32">{{ __('Actions') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($recordRows as $row)
                        <flux:table.row :key="$row['id']">
                            <flux:table.cell variant="strong">
                                {{ $row['title'] }}
                                @if ($row['subtitle'])
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $row['subtitle'] }}</flux:text>
                                @endif

                                @php
                                    $meta = collect([
                                        $row['relationship'] !== '' ? $row['relationship'] : null,
                                        $row['legislation_type'] !== '' ? $row['legislation_type'] : null,
                                        $row['date_enacted'] !== '—' ? $row['date_enacted'] : null,
                                    ])->filter()->implode(' • ');
                                @endphp

                                @if ($meta !== '')
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $meta }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm" :color="$row['status_color']">{{ $row['status_label'] }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex min-w-28 items-center justify-end gap-1">
                                    @if ($row['action'] === 'review' && $row['source_document_id'] !== null)
                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            wire:click="startReviewDocument({{ $row['source_document_id'] }})"
                                        >
                                            {{ __('Review') }}
                                        </flux:button>
                                    @elseif ($row['action'] === 'retry' && $row['source_document_id'] !== null)
                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            wire:click="startReviewDocument({{ $row['source_document_id'] }})"
                                        >
                                            {{ __('Retry') }}
                                        </flux:button>
                                    @endif

                                    @if ($row['kind'] === 'source' && $row['source_document_id'] !== null)
                                        <flux:modal.trigger name="confirm-source-delete">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                x-on:click="setDeleteConfirmation({{ $row['source_document_id'] }}, @js($row['title']), @js(__('source')))"
                                            />
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal name="confirm-source-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="`${@js(__('Delete this'))} ${deleteConfirmation.noun || @js(__('record'))}?`"></flux:heading>
                <flux:text>
                    <span
                        x-show="deleteConfirmation.title"
                        x-text="`${@js(__('This will permanently remove'))} “${deleteConfirmation.title}” ${@js(__('from records.'))}`"
                    ></span>
                    <span x-show="! deleteConfirmation.title">{{ __('This will permanently remove the selected item from records.') }}</span>
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
                        x-on:click="$wire.confirmDeletion(deleteConfirmation.id); resetDeleteConfirmation()"
                        x-bind:disabled="! deleteConfirmation.id"
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>

    @if ($this->hasAnalysisState())
        <flux:modal
            name="review-record"
            :show="$this->hasAnalysisState()"
            x-on:close="$wire.resetSourceFlow()"
            x-on:cancel="$wire.resetSourceFlow()"
            class="!max-w-[52rem] w-[calc(100vw-2rem)] lg:!w-[52rem]"
        >
            <form wire:submit="saveAnalyzedLegislation" class="space-y-6">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-2">
                        <flux:heading size="lg">{{ __('Review record') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Source: :source', ['source' => $analysisSourceLabel]) }}
                        </flux:text>
                    </div>
                </div>

                @if ($analysisWarnings !== [])
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                        <flux:heading size="sm">{{ __('Needs review') }}</flux:heading>
                        <div class="mt-2 space-y-1">
                            @foreach ($analysisWarnings as $warning)
                                <div>{{ $warning }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($analysisDuplicateCandidates !== [])
                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="space-y-3">
                            <div>
                                <flux:heading size="sm">{{ __('Possible match found') }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('This source looks similar to a record already stored for this jurisdiction. Choose whether to create a new one or update the existing record.') }}
                                </flux:text>
                            </div>

                            <flux:radio.group wire:model="analysisSaveMode" variant="buttons" class="w-full *:flex-1" size="sm">
                                <flux:radio value="create">{{ __('Create new record') }}</flux:radio>
                                <flux:radio value="update">{{ __('Update existing record') }}</flux:radio>
                            </flux:radio.group>

                            @if ($analysisSaveMode === 'update')
                                <flux:select wire:model="analysisExistingLegislationId" :invalid="$errors->has('analysisExistingLegislationId')" :label="__('Existing record')">
                                    @foreach ($analysisDuplicateCandidates as $candidate)
                                        <flux:select.option :value="$candidate['id']">
                                            {{ $candidate['title'] }}
                                        </flux:select.option>
                                    @endforeach
                                </flux:select>
                            @endif
                        </div>
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="analysisTitle" :invalid="$errors->has('analysisTitle')" :label="__('Title')" />
                    <flux:input wire:model="analysisShortTitle" :invalid="$errors->has('analysisShortTitle')" :label="__('Short title')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:select wire:model="analysisType" :invalid="$errors->has('analysisType')" :label="__('Type')">
                        @foreach ($legislationTypes as $legislationType)
                            <flux:select.option :value="$legislationType->value">{{ \Illuminate\Support\Str::headline($legislationType->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="analysisDateEnacted" :invalid="$errors->has('analysisDateEnacted')" :label="__('Date enacted')" type="date" />

                    <flux:select wire:model="analysisRelationshipType" :invalid="$errors->has('analysisRelationshipType')" :label="__('Relationship')">
                        @foreach ($legislationRelationshipTypes as $relationshipType)
                            <flux:select.option :value="$relationshipType->value">{{ \Illuminate\Support\Str::headline($relationshipType->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:textarea wire:model="analysisSummary" :invalid="$errors->has('analysisSummary')" :label="__('Summary')" rows="4" />

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ __('Save record') }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
