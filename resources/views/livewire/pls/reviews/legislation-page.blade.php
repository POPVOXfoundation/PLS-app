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
    <flux:card id="upload-legislation" class="max-w-3xl">
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
                        {{ __('Add primary or secondary legislation, including Acts, bills, regulations, statutory instruments, rules, orders, or ordinances. PLSAssist will read each source and prepare a structured record for review.') }}
                    </flux:text>

                    @if ($hasUploadedLegislationSource)
                        <flux:text x-show="! uploadOpen" x-cloak class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('One or more legislation sources have been uploaded. Expand this panel to add another Act, regulation, or related source.') }}
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
                        :heading="__('Drag a legislation source here or choose a file')"
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
                        {{ __('Moving the legislation source into the review workspace. Once uploaded, PLSAssist will start reading it in the background.') }}
                    </flux:text>
                </flux:field>
            </div>

            <div class="flex items-center gap-2 text-sm text-zinc-500 dark:text-zinc-400" wire:loading.flex wire:target="sourceUpload">
                <flux:icon icon="arrow-path" class="size-4 animate-spin text-sky-500" />
                <span>{{ __('Adding the legislation source to records...') }}</span>
            </div>
        </div>
    </flux:card>

    <flux:card wire:poll.2s.keep-alive="refreshPendingAnalyses" class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Legislation analysis') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('PLSAssist reads each uploaded Act, bill, regulation, or delegated instrument, extracts the key details, and keeps each record here for review.') }}
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
                {{ __('No records saved for this review yet. Upload a primary or secondary legislation source to start analysis.') }}
            </flux:text>
        @else
            <div class="space-y-4">
                @foreach ($recordRows as $row)
                    <section
                        x-data="{ expanded: @js($loop->first || $row['status'] !== 'saved') }"
                        class="rounded-lg border border-zinc-200 bg-zinc-50/60 p-4 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <button
                                type="button"
                                x-on:click="expanded = ! expanded"
                                class="group flex min-w-0 flex-1 items-start gap-3 text-left"
                                x-bind:aria-expanded="expanded.toString()"
                            >
                                <span class="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition group-hover:bg-zinc-100 group-hover:text-zinc-900 dark:text-zinc-400 dark:group-hover:bg-zinc-800 dark:group-hover:text-white">
                                    <flux:icon icon="chevron-right" class="size-4 transition-transform" x-bind:class="expanded ? 'rotate-90' : ''" />
                                </span>

                                <span class="min-w-0 space-y-1">
                                    <span class="flex flex-wrap items-center gap-2">
                                        <flux:badge size="sm">{{ $row['record_label'] }}</flux:badge>
                                        <flux:heading size="base" class="truncate">{{ $row['title'] }}</flux:heading>
                                        <span x-show="! expanded" x-cloak class="text-xs font-medium text-zinc-400 dark:text-zinc-500">{{ __('Expand') }}</span>
                                    </span>

                                    @php
                                        $inlineMeta = collect([
                                            $row['date_enacted'] !== '—' ? $row['date_enacted'] : null,
                                            $row['subtitle'] ?: null,
                                        ])->filter()->implode(' • ');
                                    @endphp

                                    @if ($inlineMeta !== '')
                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $inlineMeta }}</flux:text>
                                    @endif
                                </span>
                            </button>

                            <div class="flex flex-wrap items-center gap-2 sm:justify-end">
                                <div>
                                    <flux:badge size="sm" :color="$row['status_color']" :class="$row['status_badge_class']">
                                        {{ $row['status_label'] }}
                                    </flux:badge>
                                </div>

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

                                @if ($row['source_document_id'] !== null && $row['status'] === 'saved' && $row['preparation_status'] === 'processing')
                                    <flux:button size="sm" variant="subtle" icon="arrow-path" disabled>
                                        {{ __('Preparing prompts') }}
                                    </flux:button>
                                @elseif ($row['source_document_id'] !== null && $row['status'] === 'saved' && $row['scrutiny_preparation'] === [])
                                    <flux:button size="sm" variant="subtle" icon="sparkles" wire:click="prepareScrutinyPrompts({{ $row['source_document_id'] }})">
                                        {{ __('Prepare scrutiny prompts') }}
                                    </flux:button>
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

                        <div x-show="expanded" x-cloak x-transition>
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
                                @if ($row['preparation_status'] === 'processing')
                                    <div class="mt-4 rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900 dark:border-sky-900/60 dark:bg-sky-950/30 dark:text-sky-100">
                                        <div class="flex items-start gap-3">
                                            <flux:icon icon="arrow-path" class="mt-0.5 size-4 shrink-0 animate-spin" />
                                            <div class="space-y-1">
                                                <div class="font-medium">{{ __('Preparing scrutiny prompts') }}</div>
                                                <div>{{ __('PLSAssist is reading the saved source to prepare source-grounded milestones, obligations, and records to locate. Your saved legislation record remains unchanged.') }}</div>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
                                    <div class="space-y-4">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Summary') }}</div>

                                                @if ($row['source_document_id'] !== null && $row['summary'])
                                                    <button
                                                        type="button"
                                                        wire:click="viewSourceInsight({{ $row['source_document_id'] }}, @js($row['summary']), 'summary')"
                                                        class="text-xs font-medium text-violet-600 hover:text-violet-800 dark:text-violet-400 dark:hover:text-violet-300"
                                                    >
                                                        {{ __('Find supporting text') }}
                                                    </button>
                                                @endif
                                            </div>
                                            <p class="mt-1 text-sm leading-6 text-zinc-700 dark:text-zinc-300">
                                                {{ $row['summary'] ?: __('No summary extracted yet.') }}
                                            </p>
                                        </div>

                                        @if ($row['notable_excerpts'] !== [])
                                            <div>
                                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Notable excerpts') }}</div>
                                                <div class="mt-2 space-y-2">
                                                    @foreach (array_slice($row['notable_excerpts'], 0, 2) as $excerpt)
                                                        @if ($row['source_document_id'] !== null)
                                                            <button
                                                                type="button"
                                                                wire:click="viewSourceInsight({{ $row['source_document_id'] }}, @js($excerpt), 'excerpt')"
                                                                class="block w-full rounded-lg bg-white px-3 py-2 text-left text-sm leading-6 text-zinc-600 ring-1 ring-zinc-200 transition hover:bg-violet-50 hover:text-violet-900 hover:ring-violet-200 dark:bg-zinc-950/50 dark:text-zinc-300 dark:ring-zinc-800 dark:hover:bg-violet-500/10 dark:hover:text-violet-200 dark:hover:ring-violet-500/30"
                                                            >
                                                                "{{ $excerpt }}"
                                                            </button>
                                                        @else
                                                            <p class="rounded-lg bg-white px-3 py-2 text-sm leading-6 text-zinc-600 ring-1 ring-zinc-200 dark:bg-zinc-950/50 dark:text-zinc-300 dark:ring-zinc-800">
                                                                "{{ $excerpt }}"
                                                            </p>
                                                        @endif
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
                                                        @if ($row['source_document_id'] !== null)
                                                            <button
                                                                type="button"
                                                                wire:click="viewSourceInsight({{ $row['source_document_id'] }}, @js($theme), 'key theme')"
                                                                class="rounded-md bg-zinc-100 px-2.5 py-1 text-sm font-medium text-zinc-700 transition hover:bg-violet-100 hover:text-violet-800 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-violet-500/20 dark:hover:text-violet-200"
                                                            >
                                                                {{ $theme }}
                                                            </button>
                                                        @else
                                                            <flux:badge size="sm">{{ $theme }}</flux:badge>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>

                                @if ($row['scrutiny_preparation'] !== [])
                                    @php
                                        $preparationCount = collect($row['scrutiny_preparation'])->sum(fn (array $group): int => count($group['items']));
                                    @endphp

                                    <section
                                        x-data="{ focus: 'all', expandedGroups: {} }"
                                        class="mt-5 border-t border-zinc-200 pt-4 dark:border-zinc-800"
                                    >
                                        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                                            <div class="max-w-2xl space-y-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <flux:heading size="base">{{ __('Scrutiny preparation') }}</flux:heading>
                                                    <flux:badge size="sm">{{ trans_choice(':count item|:count items', $preparationCount, ['count' => $preparationCount]) }}</flux:badge>
                                                </div>
                                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Source-grounded milestones, obligations, and records to locate. Open an item to check the passage or ask PLSAssist about it.') }}
                                                </flux:text>
                                            </div>

                                            <div class="flex flex-wrap gap-1.5" role="group" aria-label="{{ __('Focus scrutiny preparation') }}">
                                                <button
                                                    type="button"
                                                    x-on:click="focus = 'all'"
                                                    class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                                                    x-bind:class="focus === 'all' ? 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'"
                                                >
                                                    {{ __('All') }}
                                                </button>
                                                @foreach ($row['scrutiny_preparation'] as $group)
                                                    <button
                                                        type="button"
                                                        x-on:click="focus = @js($group['key'])"
                                                        class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                                                        x-bind:class="focus === @js($group['key']) ? 'bg-violet-100 text-violet-800 dark:bg-violet-500/20 dark:text-violet-200' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300 dark:hover:bg-zinc-700'"
                                                    >
                                                        {{ $group['title'] }}
                                                    </button>
                                                @endforeach
                                            </div>
                                        </div>

                                        <div class="mt-4 grid gap-4 xl:grid-cols-2">
                                            @foreach ($row['scrutiny_preparation'] as $group)
                                                <section x-show="focus === 'all' || focus === @js($group['key'])" x-cloak class="border-l-2 border-violet-200 pl-4 dark:border-violet-500/30">
                                                    <div class="flex items-start gap-2">
                                                        <flux:icon :icon="$group['icon']" class="mt-0.5 size-4 shrink-0 text-violet-600 dark:text-violet-400" />
                                                        <div class="min-w-0">
                                                            <div class="text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ $group['title'] }}</div>
                                                            <div class="mt-0.5 text-xs leading-5 text-zinc-500 dark:text-zinc-400">{{ $group['description'] }}</div>
                                                        </div>
                                                    </div>

                                                    <div class="mt-3 space-y-2">
                                                        @foreach ($group['items'] as $item)
                                                            <button
                                                                type="button"
                                                                x-show="focus !== 'all' || expandedGroups[@js($group['key'])] || {{ $loop->index < 4 ? 'true' : 'false' }}"
                                                                x-cloak
                                                                wire:click="viewSourceInsight({{ $row['source_document_id'] }}, @js($item['source_text']), @js($group['title']))"
                                                                class="block w-full rounded-md border border-zinc-200 bg-white px-3 py-2 text-left transition hover:border-violet-200 hover:bg-violet-50/60 dark:border-zinc-800 dark:bg-zinc-950/40 dark:hover:border-violet-500/30 dark:hover:bg-violet-500/10"
                                                            >
                                                                <span class="block text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $item['title'] }}</span>
                                                                <span class="mt-0.5 block text-xs leading-5 text-zinc-600 dark:text-zinc-300">{{ $item['detail'] }}</span>
                                                                <span class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] font-medium text-violet-700 dark:text-violet-300">
                                                                    @if ($item['timing'] !== null)
                                                                        <span>{{ $item['timing'] }}</span>
                                                                    @endif
                                                                    <span>{{ __('View source') }}</span>
                                                                </span>
                                                            </button>
                                                        @endforeach
                                                    </div>

                                                    @if (count($group['items']) > 4)
                                                        <button
                                                            type="button"
                                                            x-show="focus === 'all'"
                                                            x-cloak
                                                            x-on:click="expandedGroups[@js($group['key'])] = ! expandedGroups[@js($group['key'])]"
                                                            class="mt-2 text-xs font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-200"
                                                        >
                                                            <span x-show="! expandedGroups[@js($group['key'])]">{{ __('Show :count more', ['count' => count($group['items']) - 4]) }}</span>
                                                            <span x-show="expandedGroups[@js($group['key'])]" x-cloak>{{ __('Show fewer') }}</span>
                                                        </button>
                                                    @endif
                                                </section>
                                            @endforeach
                                        </div>
                                    </section>
                                @endif
                            @endif
                        </div>
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
                                                variant="subtle"
                                                size="sm"
                                                icon="trash"
                                                x-on:click="setDeleteConfirmation({{ $row['source_document_id'] }}, @js($row['title']), @js(__('source')))"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    @endif
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>

            @php
                $hasDelegatedLegislation = collect($recordRows)->contains(
                    fn (array $row): bool => \Illuminate\Support\Str::lower($row['relationship']) === 'delegated',
                );
            @endphp

            @if (! $hasDelegatedLegislation)
                <div class="flex flex-col gap-3 border-t border-zinc-200 pt-4 text-sm sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800">
                    <p class="text-zinc-600 dark:text-zinc-400">
                        {{ __('No delegated or secondary legislation is recorded yet. Add regulations, orders, rules, or other instruments where they matter to this review.') }}
                    </p>
                    <a href="#upload-legislation" class="shrink-0 font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">{{ __('Add a source') }}</a>
                </div>
            @endif
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

    <flux:modal name="source-insight" class="!max-w-[56rem] w-[calc(100vw-2rem)] lg:!w-[56rem]">
        <div class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="lg">{{ __('Where this appears') }}</flux:heading>
                        @if ($sourceInsightKind !== '')
                            <flux:badge size="sm">{{ $sourceInsightKind }}</flux:badge>
                        @endif
                    </div>

                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $sourceInsightTitle }}</flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    <flux:button
                        size="sm"
                        variant="primary"
                        icon="chat-bubble-left-right"
                        wire:click="askAssistantAboutSourceInsight"
                        wire:loading.attr="disabled"
                        wire:target="askAssistantAboutSourceInsight"
                        x-on:click="$dispatch('assistant-open-requested', { prompt: @js($sourceInsightAssistantPrompt) }); window.Flux?.modal?.('source-insight')?.close?.()"
                    >
                        <span wire:loading.remove wire:target="askAssistantAboutSourceInsight">{{ __('Ask assistant') }}</span>
                        <span wire:loading wire:target="askAssistantAboutSourceInsight">{{ __('Sending...') }}</span>
                    </flux:button>

                    @if ($sourceInsightOriginalUrl !== '')
                        <flux:button size="sm" variant="subtle" icon="arrow-top-right-on-square" :href="$sourceInsightOriginalUrl" target="_blank" rel="noopener">
                            {{ __('Open original') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            <div class="rounded-xl border border-violet-200 bg-violet-50/70 px-4 py-3 dark:border-violet-500/20 dark:bg-violet-500/10">
                <div class="text-xs font-semibold uppercase tracking-wide text-violet-700 dark:text-violet-300">{{ __('Selected item') }}</div>
                <div class="mt-1 text-sm leading-6 text-violet-950 dark:text-violet-100">{{ $sourceInsightLabel }}</div>
            </div>

            @if ($sourceInsightSnippets === [])
                <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-4 text-sm leading-6 text-zinc-600 dark:border-zinc-800 dark:bg-zinc-950/60 dark:text-zinc-300">
                    {{ __('No direct text matches were found in the extracted source text. Try opening the full text or asking the assistant to reason from the full uploaded source.') }}
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($sourceInsightSnippets as $snippet)
                        <section class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950/60">
                            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Source passage :number', ['number' => $loop->iteration]) }}
                                </div>

                                @if ($snippet['matched_terms'] !== [])
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($snippet['matched_terms'] as $term)
                                            <span class="rounded bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ $term }}</span>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <p class="text-sm leading-6 text-zinc-700 dark:text-zinc-200">
                                {!! $this->highlightedSourceSnippet($snippet['snippet']) !!}
                            </p>
                        </section>
                    @endforeach
                </div>
            @endif
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
                    <flux:select wire:model="analysisType" :invalid="$errors->has('analysisType')" :label="__('Instrument type')">
                        @foreach ($legislationTypes as $legislationType)
                            <flux:select.option :value="$legislationType->value">{{ $this->legislationTypeLabel($legislationType) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="analysisDateEnacted" :invalid="$errors->has('analysisDateEnacted')" :label="__('Date enacted')" type="date" />

                    <flux:select wire:model="analysisRelationshipType" :invalid="$errors->has('analysisRelationshipType')" :label="__('Role in review')">
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
