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
    <flux:card>
        <div
            x-data="{ uploadOpen: @js(! $hasUploadedLegislationSource), uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploadOpen = true; uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            class="space-y-5"
        >
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Upload legislation') }}</flux:heading>
                    <flux:text x-show="uploadOpen" x-cloak class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Add the law, bill, regulation, or source text. PLSAssist will read it and prepare a structured record for you to review.') }}
                    </flux:text>

                    @if ($hasUploadedLegislationSource)
                        <flux:text x-show="! uploadOpen" x-cloak class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('A legislation source has been uploaded. Expand this panel to add another source.') }}
                        </flux:text>
                    @endif
                </div>

                @if ($hasUploadedLegislationSource)
                    <flux:button type="button" size="sm" variant="subtle" x-on:click="uploadOpen = ! uploadOpen">
                        <span x-show="! uploadOpen">{{ __('Add another source') }}</span>
                        <span x-show="uploadOpen" x-cloak>{{ __('Collapse') }}</span>
                    </flux:button>
                @endif
            </div>

            <div class="space-y-4" x-show="uploadOpen" x-cloak x-transition>
                <flux:file-upload
                    wire:model="sourceUpload"
                    accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                    :label="__('Source file')"
                >
                    <flux:file-upload.dropzone
                        class="!min-h-28 !py-4"
                        :heading="__('Drag source here or choose a file')"
                        :text="__('PDF or DOCX, :limit', ['limit' => $this->sourceUploadLimitLabel()])"
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
                        {{ __('Moving the source into the review workspace. Once uploaded, PLSAssist will start reading it in the background.') }}
                    </flux:text>
                </flux:field>
            </div>

            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" wire:loading.flex wire:target="sourceUpload">
                <flux:icon icon="arrow-path" class="size-4 animate-spin text-sky-500" />
                <span>{{ __('Adding the source to records...') }}</span>
            </div>
        </div>
    </flux:card>

    <flux:card wire:poll.2s.keep-alive="refreshPendingAnalyses" class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Legislation analysis') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('PLSAssist reads each uploaded source, extracts the key legislation details, and keeps the record here for review.') }}
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
                {{ __('No records saved for this review yet. Upload a source file to start legislation analysis.') }}
            </flux:text>
        @else
            <div class="space-y-4">
                @foreach ($recordRows as $row)
                    <section class="rounded-lg border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/50">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0 space-y-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="base">{{ $row['title'] }}</flux:heading>
                                    <flux:badge size="sm" :color="$row['status_color']" :class="$row['status_badge_class']">
                                        {{ $row['status_label'] }}
                                    </flux:badge>
                                </div>

                                @php
                                    $inlineMeta = collect([
                                        $row['relationship'] !== '' ? $row['relationship'] : null,
                                        $row['legislation_type'] !== '' ? $row['legislation_type'] : null,
                                        $row['date_enacted'] !== '—' ? $row['date_enacted'] : null,
                                    ])->filter()->implode(' • ');
                                @endphp

                                @if ($inlineMeta !== '')
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $inlineMeta }}</flux:text>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                @if ($row['source_document_id'] !== null)
                                    <flux:button size="sm" variant="subtle" icon="document-text" wire:click="viewSourceText({{ $row['source_document_id'] }})">
                                        {{ __('View text') }}
                                    </flux:button>

                                    @if ($row['original_url'] !== null)
                                        <flux:button size="sm" variant="ghost" icon="arrow-top-right-on-square" :href="$row['original_url']" target="_blank" rel="noopener">
                                            {{ __('Original file') }}
                                        </flux:button>
                                    @endif
                                @endif

                                @if ($row['action'] === 'review' && $row['source_document_id'] !== null)
                                    <flux:button size="sm" variant="primary" wire:click="startReviewDocument({{ $row['source_document_id'] }})">
                                        {{ __('Review and save') }}
                                    </flux:button>
                                @elseif ($row['action'] === 'edit' && $row['source_document_id'] !== null)
                                    <flux:button size="sm" variant="subtle" wire:click="startReviewDocument({{ $row['source_document_id'] }})">
                                        {{ __('Edit record') }}
                                    </flux:button>
                                @elseif ($row['action'] === 'retry' && $row['source_document_id'] !== null)
                                    <flux:button size="sm" variant="subtle" wire:click="retrySourceAnalysis({{ $row['source_document_id'] }})">
                                        {{ __('Retry') }}
                                    </flux:button>
                                @endif
                            </div>
                        </div>

                        @if ($row['status'] === 'processing')
                            <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100">
                                <div class="flex items-start gap-3">
                                    <flux:icon icon="arrow-path" class="mt-0.5 size-4 shrink-0 animate-spin" />
                                    <div class="space-y-1">
                                        <div class="font-medium">{{ __('What PLSAssist is doing') }}</div>
                                        <div>{{ $row['status_detail'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @elseif ($row['status'] === 'failed')
                            <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-900 dark:border-rose-900/60 dark:bg-rose-950/30 dark:text-rose-100">
                                {{ $row['status_detail'] }}
                            </div>
                        @else
                            <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                                <div class="space-y-4">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Summary') }}</div>
                                        <p class="mt-1 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                                            {{ $row['summary'] ?: __('No summary extracted yet.') }}
                                        </p>
                                    </div>

                                    @if ($row['notable_excerpts'] !== [])
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Notable excerpts') }}</div>
                                            <div class="mt-2 space-y-2">
                                                @foreach (array_slice($row['notable_excerpts'], 0, 2) as $excerpt)
                                                    <p class="rounded-lg bg-white px-3 py-2 text-sm leading-6 text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:text-zinc-300 dark:ring-zinc-800">
                                                        "{{ $excerpt }}"
                                                    </p>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </div>

                                <div class="space-y-4">
                                    <div>
                                        <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Key themes') }}</div>
                                        @if ($row['key_themes'] === [])
                                            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No themes extracted yet.') }}</flux:text>
                                        @else
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($row['key_themes'] as $theme)
                                                    <flux:badge size="sm">{{ $theme }}</flux:badge>
                                                @endforeach
                                            </div>
                                        @endif
                                    </div>

                                    @if ($row['important_dates'] !== [])
                                        <div>
                                            <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Dates mentioned') }}</div>
                                            <div class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($row['important_dates'] as $date)
                                                    <flux:badge size="sm">{{ $date }}</flux:badge>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if ($row['warnings'] !== [])
                                        <div class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                                            <div class="font-medium">{{ __('Needs verification') }}</div>
                                            <ul class="mt-1 space-y-1">
                                                @foreach ($row['warnings'] as $warning)
                                                    <li>{{ $warning }}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <div class="border-t border-zinc-200 pt-2 dark:border-zinc-800">
                <flux:heading size="base">{{ __('Source history') }}</flux:heading>
            </div>

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
                                @if (in_array($row['action'], ['review', 'edit'], true) && $row['source_document_id'] !== null)
                                    <button
                                        type="button"
                                        wire:click="startReviewDocument({{ $row['source_document_id'] }})"
                                        class="text-left text-base font-normal text-zinc-900 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300"
                                    >
                                        {{ $row['title'] }}
                                    </button>
                                @else
                                    {{ $row['title'] }}
                                @endif

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
                                <flux:badge size="sm" :color="$row['status_color']" :class="$row['status_badge_class']">
                                    {{ $row['status_label'] }}
                                </flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex min-w-28 items-center justify-end gap-1">
                                    @if ($row['source_document_id'] !== null)
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="document-text"
                                            wire:click="viewSourceText({{ $row['source_document_id'] }})"
                                        >
                                            {{ __('Text') }}
                                        </flux:button>

                                        @if ($row['original_url'] !== null)
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="arrow-top-right-on-square"
                                                :href="$row['original_url']"
                                                target="_blank"
                                                rel="noopener"
                                            >
                                                {{ __('Original') }}
                                            </flux:button>
                                        @endif
                                    @endif

                                    @if ($row['action'] === 'review' && $row['source_document_id'] !== null)
                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            wire:click="startReviewDocument({{ $row['source_document_id'] }})"
                                        >
                                            {{ __('Review') }}
                                        </flux:button>
                                    @elseif ($row['action'] === 'edit' && $row['source_document_id'] !== null)
                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            wire:click="startReviewDocument({{ $row['source_document_id'] }})"
                                        >
                                            {{ __('Edit') }}
                                        </flux:button>
                                    @elseif ($row['action'] === 'retry' && $row['source_document_id'] !== null)
                                        <flux:button
                                            size="sm"
                                            variant="subtle"
                                            wire:click="retrySourceAnalysis({{ $row['source_document_id'] }})"
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

    <flux:modal name="source-text" class="!max-w-[64rem] w-[calc(100vw-2rem)] lg:!w-[64rem]">
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <flux:heading size="lg">{{ __('Extracted source text') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $sourceTextTitle }}</flux:text>
                </div>

                @if ($sourceTextOriginalUrl !== '')
                    <flux:button size="sm" variant="subtle" icon="arrow-top-right-on-square" :href="$sourceTextOriginalUrl" target="_blank" rel="noopener">
                        {{ __('Open original') }}
                    </flux:button>
                @endif
            </div>

            <div class="max-h-[65vh] overflow-y-auto rounded-xl border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                <pre class="whitespace-pre-wrap break-words font-mono text-xs leading-5 text-zinc-700 dark:text-zinc-200">{{ $sourceTextContent }}</pre>
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
                        <flux:heading size="lg">{{ $this->analysisModalHeading() }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Source: :source', ['source' => $analysisSourceLabel]) }}
                        </flux:text>
                    </div>
                </div>

                @if ($analysisWarnings !== [])
                    <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                        <flux:heading size="sm">{{ $this->analysisWarningsHeading() }}</flux:heading>
                        <div class="mt-2 space-y-1">
                            @foreach ($analysisWarnings as $warning)
                                <div>{{ $warning }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($analysisDuplicateCandidates !== [] && $analysisStatus !== 'saved')
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

                <div class="grid gap-4 lg:grid-cols-[minmax(0,0.9fr)_minmax(0,1.4fr)]">
                    <div class="space-y-4">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <flux:heading size="sm">{{ __('Themes') }}</flux:heading>
                            @if ($analysisKeyThemes === [])
                                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No themes extracted.') }}</flux:text>
                            @else
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($analysisKeyThemes as $theme)
                                        <flux:badge size="sm">{{ $theme }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <flux:heading size="sm">{{ __('Dates mentioned') }}</flux:heading>
                            @if ($analysisImportantDates === [])
                                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No dates extracted.') }}</flux:text>
                            @else
                                <div class="mt-3 flex flex-wrap gap-2">
                                    @foreach ($analysisImportantDates as $date)
                                        <flux:badge size="sm">{{ $date }}</flux:badge>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <flux:heading size="sm">{{ __('Notable excerpts') }}</flux:heading>
                        @if ($analysisNotableExcerpts === [])
                            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ __('No excerpts extracted.') }}</flux:text>
                        @else
                            <div class="mt-3 space-y-3">
                                @foreach ($analysisNotableExcerpts as $excerpt)
                                    <div class="rounded-xl bg-white/80 p-3 dark:bg-zinc-950/50">
                                        <flux:text class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">“{{ $excerpt }}”</flux:text>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div class="flex justify-end">
                    <flux:button variant="primary" type="submit">{{ $this->analysisSubmitLabel() }}</flux:button>
                </div>
            </form>
        </flux:modal>
    @endif
</div>
