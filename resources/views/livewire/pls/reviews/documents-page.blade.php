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
    <flux:heading size="xl">{{ __('Documents & Sources') }}</flux:heading>
    <flux:text>{{ __('The full record: legislation, contextual material, templates and stakeholder submissions. Evidence is the support or contradiction you link to a finding in Analysis; a document in this register is not automatically verified evidence.') }}</flux:text>
    <livewire:pls.reviews.record-intake :review="$review" />
    <div class="flex flex-wrap gap-2">
        <flux:button :href="route('pls.reviews.documents.register', [$review, 'format' => 'xlsx'])" icon="arrow-down-tray">{{ __('Download register (Excel)') }}</flux:button>
        <flux:button :href="route('pls.reviews.documents.register', $review)" icon="arrow-down-tray">{{ __('Download register (CSV)') }}</flux:button>
    </div>
    <div class="flex flex-wrap gap-3" aria-label="Document processing counts">
        @foreach(['' => 'All sources', 'ready' => 'Text ready', 'queued' => 'Queued', 'processing' => 'Reading text', 'needs_attention' => 'Needs attention', 'uploaded' => 'Text unavailable'] as $key => $label)
            <flux:button size="sm" wire:click="$set('statusFilter', '{{ $key }}')">{{ __($label) }} · {{ $key === '' ? $review->documents->count() : $review->documents->filter(fn ($doc) => $doc->processingStatus() === $key)->count() }}</flux:button>
        @endforeach
    </div>
    @if ($hasLegislationSources)
        <details><summary class="cursor-pointer text-sm text-violet-700">{{ __('Stakeholder suggestions') }}</summary>
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

    @if ($hasLegislationSources)</details>@endif

    @if ($review->documents->contains('document_type', \App\Domain\Documents\Enums\DocumentType::LegislationText))
        <flux:card class="space-y-3">
            <flux:heading>{{ __('Legislation in the record') }}</flux:heading>
            <flux:text>{{ __('Legislation is included in assistant responses. Legislative sources uploaded here are also available in Legislation for review and confirmation.') }}</flux:text>
            <ul class="space-y-2">
                @foreach ($review->documents->where('document_type', \App\Domain\Documents\Enums\DocumentType::LegislationText) as $source)
                    <li><flux:link :href="route('pls.reviews.documents.show', [$review, $source])" wire:navigate>{{ $source->title }}</flux:link></li>
                @endforeach
            </ul>
        </flux:card>
    @endif

    <flux:card wire:poll.10s="refreshPendingAnalyses" class="space-y-5">
        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Complete document register') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Processing reads text; team review checks the source. These are separate statuses. Open a title for details or use Original to view the source.') }}
                </flux:text>
            </div>

            @if ($hasProcessingRecords)
                <div class="flex items-center gap-2 rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-sm text-sky-700 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-200">
                    <flux:icon icon="arrow-path" class="size-4 animate-spin" />
                    <span>{{ __('Processing') }}</span>
                </div>
            @endif
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <flux:input wire:model.live.debounce.300ms="search" :label="__('Search documents')" placeholder="Title or summary" type="search" />
            <flux:select wire:model.live="typeFilter" :label="__('Document type')"><option value="">{{ __('All types') }}</option>@foreach (\App\Domain\Documents\Enums\DocumentType::cases() as $type)<option value="{{ $type->value }}">{{ \Illuminate\Support\Str::headline($type->value) }}</option>@endforeach</flux:select>
            <flux:select wire:model.live="sortOrder" :label="__('Sort by')"><option value="newest">{{ __('Recently updated') }}</option><option value="title">{{ __('Title') }}</option></flux:select>
        </div>
        <flux:select wire:model.live="reviewFilter" :label="__('Team review')"><option value="">{{ __('All review states') }}</option><option value="unchecked">{{ __('Not checked') }}</option><option value="checked">{{ __('Checked by team') }}</option><option value="excluded">{{ __('Excluded from analysis') }}</option></flux:select>
        @if ($recordRows === [])
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $search !== '' || $typeFilter !== '' ? __('No documents match these filters. Clear them to see all records.') : __('No documents or sources added yet.') }}
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
                            <a href="{{ route('pls.reviews.documents.show', [$review, $row['id']]) }}" wire:navigate
                                class="block max-w-full break-words text-left text-base text-violet-700 underline decoration-violet-300 underline-offset-4 hover:text-violet-900 dark:text-violet-300">
                                {{ $row['title'] }}
                            </a>

                            <p class="text-xs text-zinc-500">{{ $row['review_status'] }}</p>
                            <p class="text-xs text-zinc-500">{{ __('Source / organisation') }}: {{ $row['organisation'] ?: __('Not recorded') }} · {{ __('Source date') }}: {{ $row['source_date'] ?: __('Not recorded') }}</p>
                            <p class="text-xs text-zinc-500">{{ __('Added by') }}: {{ $row['added_by'] }} @if($row['linked']) · {{ __('Linked to') }}: {{ $row['linked'] }} @endif</p>
                            @if($row['original_url'])<flux:link :href="$row['original_url']" target="_blank" rel="noopener noreferrer">{{ __('Original document') }}</flux:link>@endif

                            @if ($row['warnings'] !== [])
                                <p class="text-sm text-amber-800 dark:text-amber-300">{{ implode(' ', $row['warnings']) }}</p>
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
                                    <flux:button aria-label="Delete record"
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
