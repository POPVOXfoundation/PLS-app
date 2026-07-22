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
    <flux:card class="space-y-5">
        <div
            x-data="{ uploading: false, progress: 0 }"
            x-on:livewire-upload-start="uploading = true; progress = 0"
            x-on:livewire-upload-finish="uploading = false; progress = 100"
            x-on:livewire-upload-cancel="uploading = false; progress = 0"
            x-on:livewire-upload-error="uploading = false"
            x-on:livewire-upload-progress="progress = $event.detail.progress"
            class="space-y-5"
        >
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-2xl space-y-2">
                    <flux:heading size="lg">{{ __('Evidence') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Upload review evidence and source materials. PLSAssist will read each file and keep the record here for review.') }}
                    </flux:text>
                </div>

                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('PDF, DOCX, TXT, or MD') }}</flux:text>
            </div>

            @can('update', $review)
                <div class="space-y-4">
                    <flux:file-upload
                        wire:model="documentUploads"
                        accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown"
                        multiple
                        :label="__('Add evidence')"
                    >
                        <flux:file-upload.dropzone
                            class="!min-h-24 !py-3"
                            :heading="__('Drag files here or choose files')"
                            :text="__('PDF, DOCX, TXT, or MD, :limit', ['limit' => $this->uploadLimitLabel()])"
                        />
                    </flux:file-upload>

                    <flux:field>
                        <flux:error name="documentUploads" />
                        <flux:error name="documentUploads.*" />
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
                            {{ __('Moving evidence into the review workspace. Once uploaded, PLSAssist will start reading it in the background.') }}
                        </flux:text>
                    </flux:field>
                </div>

                <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" wire:loading.flex wire:target="documentUploads">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin text-sky-500" />
                    <span>{{ __('Adding evidence to records...') }}</span>
                </div>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('You can review saved evidence records here, but only contributors can upload or edit them.') }}
                </flux:text>
            @endcan
        </div>
    </flux:card>

    @if ($hasLegislationSources)
        <flux:card class="space-y-4 border-violet-200 bg-violet-50/40 dark:border-violet-500/30 dark:bg-violet-500/5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="base">{{ __('Stakeholder suggestions from legislation') }}</flux:heading>
                        @if ($legislationStakeholderSuggestions->isNotEmpty())
                            <flux:badge size="sm">{{ __(':count suggestions', ['count' => $legislationStakeholderSuggestions->count()]) }}</flux:badge>
                        @endif
                    </div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('PLSAssist identifies implementing agencies, oversight bodies, and affected groups from the legislation. Review and add the suggestions in Stakeholders.') }}
                    </flux:text>
                </div>

                @if ($legislationStakeholderSuggestions->isNotEmpty())
                    <flux:button size="sm" variant="primary" :href="route('pls.reviews.stakeholders', ['review' => $review])" wire:navigate icon="user-group">
                        {{ __('Review suggestions') }}
                    </flux:button>
                @endif
            </div>

            @if ($hasProcessingLegislationSuggestions)
                <div class="flex items-start gap-3 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100">
                    <flux:icon icon="arrow-path" class="mt-0.5 size-4 shrink-0 animate-spin" />
                    <div>{{ __('PLSAssist is reading the legislation and preparing stakeholder suggestions. Your saved legislation record remains unchanged.') }}</div>
                </div>
            @elseif ($legislationStakeholderSuggestions->isEmpty())
                <div class="flex flex-col gap-3 rounded-lg border border-dashed border-zinc-300 bg-zinc-50/70 px-4 py-4 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-700 dark:bg-zinc-900/40">
                    @if ($hasGeneratedLegislationStakeholderSuggestions)
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('All current suggestions from the legislation have been reviewed. Visit Stakeholders to see the recorded directory.') }}
                        </flux:text>

                        <flux:button size="sm" variant="subtle" :href="route('pls.reviews.stakeholders', ['review' => $review])" wire:navigate icon="user-group">
                            {{ __('Open stakeholders') }}
                        </flux:button>
                    @else
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('No suggestions have been generated from the legislation yet. Generate them to identify source-grounded stakeholders and implementing agencies.') }}
                        </flux:text>

                        @can('update', $review)
                            <flux:button size="sm" variant="subtle" icon="sparkles" wire:click="generateLegislationStakeholderSuggestions">
                                {{ __('Generate suggestions') }}
                            </flux:button>
                        @endcan
                    @endif
                </div>
            @else
                <div class="grid gap-x-6 gap-y-3 md:grid-cols-2 xl:grid-cols-3">
                    @foreach ($legislationStakeholderSuggestions->take(6) as $suggestion)
                        <section class="border-l-2 border-violet-200 pl-3 dark:border-violet-500/30">
                            <div class="flex flex-wrap items-center gap-2">
                                <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $suggestion['name'] }}</div>
                                <flux:badge size="sm" :color="$suggestion['kind'] === 'implementing_agency' ? 'sky' : 'zinc'">
                                    {{ $suggestion['kind'] === 'implementing_agency' ? __('Implementing agency') : __('Stakeholder') }}
                                </flux:badge>
                            </div>

                            @if ($suggestion['rationale'] !== '')
                                <div class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $suggestion['rationale'] }}</div>
                            @endif

                            @if ($suggestion['source'] !== '')
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Source: :source', ['source' => $suggestion['source']]) }}</div>
                            @endif

                            @if ($suggestion['source_title'] !== '')
                                <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('From: :title', ['title' => $suggestion['source_title']]) }}</div>
                            @endif
                        </section>
                    @endforeach
                </div>

                @if ($legislationStakeholderSuggestions->count() > 6)
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('More suggestions are available in Stakeholders.') }}
                    </flux:text>
                @endif
            @endif
        </flux:card>
    @endif

    <flux:card wire:poll.2s.keep-alive="refreshPendingAnalyses" class="space-y-5">
        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Records') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Uploads stay here as they process, save, or need review attention.') }}
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
                {{ __('No evidence linked to this review yet.') }}
            </flux:text>
        @else
            <div class="space-y-1">
                <div class="hidden grid-cols-[minmax(0,1fr)_9rem_7rem_8rem_auto] gap-4 border-b border-zinc-200 pb-3 text-sm font-medium text-zinc-700 dark:border-zinc-800 dark:text-zinc-300 lg:grid">
                    <div>{{ __('Record') }}</div>
                    <div>{{ __('Type') }}</div>
                    <div>{{ __('Updated') }}</div>
                    <div>{{ __('Status') }}</div>
                    <div class="text-right">{{ __('Actions') }}</div>
                </div>

                @foreach ($recordRows as $row)
                    <section
                        wire:key="evidence-record-{{ $row['id'] }}"
                        class="grid min-w-0 gap-3 border-b border-zinc-200 py-4 last:border-b-0 dark:border-zinc-800 lg:grid-cols-[minmax(0,1fr)_9rem_7rem_8rem_auto] lg:items-center lg:gap-4"
                    >
                        <div class="min-w-0 space-y-1">
                            @if (in_array($row['action'], ['review', 'edit'], true))
                                <button
                                    type="button"
                                    wire:click="startEditingDocument({{ $row['id'] }})"
                                    class="block max-w-full text-left text-base font-normal text-zinc-900 transition hover:text-violet-700 dark:text-white dark:hover:text-violet-300"
                                >
                                    <span class="break-words">{{ $row['title'] }}</span>
                                </button>
                            @else
                                <div class="break-words text-base text-zinc-900 dark:text-white">{{ $row['title'] }}</div>
                            @endif

                            @if ($row['summary'] !== '')
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ \Illuminate\Support\Str::limit($row['summary'], 180) }}
                                </flux:text>
                            @endif

                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400 lg:hidden">
                                {{ $row['document_type'] }} • {{ __('Updated :date', ['date' => $row['updated_at']]) }}
                            </flux:text>
                        </div>

                        <div class="hidden text-sm text-zinc-600 lg:block dark:text-zinc-300">{{ $row['document_type'] }}</div>
                        <div class="hidden text-sm text-zinc-600 lg:block dark:text-zinc-300">{{ $row['updated_at'] }}</div>

                        <div class="hidden lg:block">
                            <flux:badge size="sm" :color="$row['status_color']" :class="$row['status_badge_class']">
                                {{ $row['status_label'] }}
                            </flux:badge>
                        </div>

                        <div class="flex min-w-0 flex-wrap items-center gap-2 lg:justify-end">
                            <flux:badge size="sm" class="lg:hidden" :color="$row['status_color']" :class="$row['status_badge_class']">
                                {{ $row['status_label'] }}
                            </flux:badge>

                            @can('update', $review)
                                @if ($row['action'] === 'review')
                                    <flux:button
                                        size="sm"
                                        variant="subtle"
                                        wire:click="startEditingDocument({{ $row['id'] }})"
                                    >
                                        {{ __('Review') }}
                                    </flux:button>
                                @elseif ($row['action'] === 'edit')
                                    <flux:button
                                        size="sm"
                                        variant="subtle"
                                        wire:click="startEditingDocument({{ $row['id'] }})"
                                    >
                                        {{ __('Edit') }}
                                    </flux:button>
                                @endif

                                @if ($row['status'] === 'needs_attention')
                                    <flux:button
                                        size="sm"
                                        variant="subtle"
                                        wire:click="retryDocumentAnalysis({{ $row['id'] }})"
                                    >
                                        {{ __('Retry') }}
                                    </flux:button>
                                @endif

                                <flux:modal.trigger name="confirm-document-delete">
                                    <flux:button
                                        variant="subtle"
                                        size="sm"
                                        icon="trash"
                                        x-on:click="setDeleteConfirmation({{ $row['id'] }}, @js($row['title']), @js(__('document')))"
                                    >
                                        {{ __('Delete') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endcan
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:modal wire:model.self="showEditDocumentModal" scroll="body" class="!max-w-[56rem] w-[calc(100vw-2rem)] lg:!w-[56rem]">
        <form wire:submit="saveDocumentEdits" class="space-y-6">
            <div class="space-y-3">
                <div class="space-y-2">
                    <flux:heading size="lg">{{ __('Review document details') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Adjust the saved title, type, or summary if anything needs cleanup.') }}
                    </flux:text>
                </div>

                @if ($analysisStatus !== '')
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm" :color="$analysisStatus === 'needs_attention' ? 'rose' : ($analysisStatus === 'processing' ? 'sky' : 'emerald')">
                            {{ $analysisStatus === 'needs_attention' ? __('Needs attention') : ($analysisStatus === 'processing' ? __('Processing') : __('Saved')) }}
                        </flux:badge>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('AI notes are shown below for reference while you edit.') }}
                        </flux:text>
                    </div>
                @endif
            </div>

            @if ($analysisWarnings !== [])
                <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-100">
                    <flux:heading size="sm">{{ __('Warnings') }}</flux:heading>
                    <div class="mt-2 space-y-1">
                        @foreach ($analysisWarnings as $warning)
                            <div>{{ $warning }}</div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="documentTitle" :invalid="$errors->has('documentTitle')" :label="__('Title')" />
                <flux:select wire:model="documentType" :invalid="$errors->has('documentType')" :label="__('Type')">
                    @foreach ($documentTypes as $type)
                        <flux:select.option :value="$type->value">{{ $this->documentTypeLabel($type) }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea wire:model="documentSummary" :invalid="$errors->has('documentSummary')" :label="__('Summary')" rows="4" />

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

            <div class="flex items-center justify-between gap-3">
                <div>
                    @if ($analysisStatus === 'needs_attention' && $documentEditingId !== '')
                        <flux:button type="button" variant="ghost" wire:click="retryDocumentAnalysis({{ (int) $documentEditingId }})">
                            {{ __('Retry analysis') }}
                        </flux:button>
                    @endif
                </div>

                <div class="flex justify-end gap-2">
                    <flux:modal.close>
                        <flux:button variant="filled" type="button">
                            {{ __('Close') }}
                        </flux:button>
                    </flux:modal.close>

                    <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                </div>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-document-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="`${@js(__('Delete this'))} ${deleteConfirmation.noun || @js(__('record'))}?`"></flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span
                        x-show="deleteConfirmation.title"
                        x-text="`${@js(__('This will permanently remove'))} “${deleteConfirmation.title}” ${@js(__('from records.'))}`"
                    ></span>
                    <span x-show="! deleteConfirmation.title">{{ __('This will permanently remove the selected item from records.') }}</span>
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
                        x-on:click="$wire.confirmDeletion(deleteConfirmation.id); resetDeleteConfirmation()"
                        x-bind:disabled="! deleteConfirmation.id"
                        wire:loading.attr="disabled"
                        wire:target="confirmDeletion"
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
