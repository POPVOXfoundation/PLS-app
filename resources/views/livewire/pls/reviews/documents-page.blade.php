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
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Upload review documents and they will appear in the records table below.') }}
                </flux:text>
            </div>

            @can('update', $review)
                <div class="space-y-4">
                    <flux:file-upload
                        wire:model="documentUploads"
                        accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown"
                        multiple
                        :label="__('Document file')"
                    >
                        <flux:file-upload.dropzone
                            :heading="__('Choose files')"
                            :text="__('PDF, DOCX, TXT, or MD • :limit', ['limit' => $this->uploadLimitLabel()])"
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
                            {{ __('Moving the document into the review workspace.') }}
                        </flux:text>
                    </flux:field>
                </div>

                <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" wire:loading.flex wire:target="documentUploads">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin text-sky-500" />
                    <span>{{ __('Adding documents to records...') }}</span>
                </div>
            @else
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('You can review saved document records here, but only contributors can upload or edit them.') }}
                </flux:text>
            @endcan
        </div>
    </flux:card>

    <flux:card wire:poll.5s.keep-alive="refreshPendingAnalyses" class="space-y-6">
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
                {{ __('No documents linked to this review yet.') }}
            </flux:text>
        @else
            <div class="space-y-3">
                @foreach ($recordRows as $row)
                    <div
                        wire:key="document-record-{{ $row['id'] }}"
                        class="rounded-2xl border border-zinc-200 bg-white/90 p-4 shadow-sm shadow-zinc-950/5 dark:border-zinc-800 dark:bg-zinc-950/40"
                    >
                        <div class="space-y-1">
                            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-4 gap-y-1">
                                <flux:heading size="sm" class="break-words text-base/tight text-zinc-900 dark:text-white">
                                    {{ $row['title'] }}
                                </flux:heading>

                                @can('update', $review)
                                    <div class="flex items-center gap-2 self-center justify-self-end">
                                        @if ($row['status'] !== 'processing')
                                            <flux:button.group>
                                                <flux:button
                                                    size="sm"
                                                    variant="ghost"
                                                    icon="pencil-square"
                                                    wire:click="startEditingDocument({{ $row['id'] }})"
                                                />

                                                <flux:modal.trigger name="confirm-document-delete">
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="trash"
                                                        x-on:click="setDeleteConfirmation({{ $row['id'] }}, @js($row['title']), @js(__('document')))"
                                                    />
                                                </flux:modal.trigger>
                                            </flux:button.group>
                                        @else
                                            <flux:modal.trigger name="confirm-document-delete">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    x-on:click="setDeleteConfirmation({{ $row['id'] }}, @js($row['title']), @js(__('document')))"
                                                />
                                            </flux:modal.trigger>
                                        @endif
                                    </div>
                                @endcan
                            </div>

                            @if ($row['summary'] !== '')
                                <flux:text class="-mt-1 max-w-5xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                                    {{ \Illuminate\Support\Str::limit($row['summary'], 220) }}
                                </flux:text>
                            @endif

                            <div class="mt-3 flex flex-col gap-3 border-t border-zinc-200/80 pt-3 dark:border-zinc-800/80 sm:flex-row sm:items-center sm:justify-between">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Updated :date', ['date' => $row['updated_at']]) }}
                                </flux:text>

                                <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                    <flux:badge size="sm">{{ $row['document_type'] }}</flux:badge>
                                    <flux:badge size="sm" :color="$row['status_color']">{{ $row['status_label'] }}</flux:badge>

                                    @can('update', $review)
                                        @if ($row['status'] === 'needs_attention')
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                wire:click="retryDocumentAnalysis({{ $row['id'] }})"
                                            >
                                                {{ __('Retry') }}
                                            </flux:button>
                                        @endif
                                    @endcan
                                </div>
                            </div>
                        </div>
                    </div>
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
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
