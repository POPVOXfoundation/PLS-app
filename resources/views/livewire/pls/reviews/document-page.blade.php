<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="min-w-0 space-y-2">
            <flux:link :href="route($isLegislation ? 'pls.reviews.legislation' : 'pls.reviews.documents', $review)" wire:navigate>
                {{ $isLegislation ? __('Back to legislation') : __('Back to evidence') }}
            </flux:link>
            <flux:heading size="xl" class="break-words">{{ $document->title }}</flux:heading>
            <flux:text>{{ __('Document details') }}</flux:text>
        </div>
        @if ($originalAvailable)
            <flux:button :href="route('pls.reviews.documents.original', [$review, $document])" target="_blank" rel="noopener" icon="arrow-top-right-on-square">
                {{ __('Open original document') }}
            </flux:button>
        @else
            <flux:text>{{ __('The original file is unavailable. Contact the review owner to restore it.') }}</flux:text>
        @endif
    </div>

    <flux:card class="space-y-5">
        <div class="flex flex-wrap items-center gap-3">
            <flux:badge>{{ $document->evidenceRoleLabel() }}</flux:badge>
            <flux:badge>{{ $document->reviewStatusLabel() }}</flux:badge>
            @can('update', $review)<flux:button size="sm" wire:click="markReviewed">{{ __('Mark document reviewed') }}</flux:button>@endcan
        </div>
        @if ($sourceKind && ! data_get($document->metadata, 'source_identity_confirmed_at'))
            <div class="rounded-lg bg-amber-50 p-4 text-sm text-amber-950">
                <p>{{ __('Source text identifies this as: :kind. Confirm the source classification; a bill or debate must not be treated as enacted law.', ['kind' => \Illuminate\Support\Str::headline($sourceKind)]) }}</p>
                @can('update', $review)<flux:button class="mt-3" wire:click="correctSourceIdentity">{{ __('Confirm source classification') }}</flux:button>@endcan
            </div>
        @endif
        <dl class="grid gap-4 text-sm sm:grid-cols-2">
            @foreach ([__('File name') => data_get($document->metadata, 'original_name', __('Not recorded')), __('Type') => \Illuminate\Support\Str::headline($document->document_type->value), __('File size') => $document->fileSizeLabel(), __('Format') => $document->mime_type ?: __('Not recorded'), __('Uploaded') => $document->created_at->format('j M Y, H:i'), __('Updated') => $document->updated_at->format('j M Y, H:i'), __('Analysis status') => \Illuminate\Support\Str::headline($analysis['status'] ?? 'not recorded'), __('Text extraction') => \Illuminate\Support\Str::headline(data_get($document->metadata, 'extraction.status', $textChunks->total() > 0 ? 'available' : 'not available'))] as $label => $value)
                <div class="min-w-0">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt>
                    <dd class="mt-1 break-words text-zinc-900 dark:text-white">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <div class="space-y-2">
            <flux:heading>{{ __('Summary') }}</flux:heading>
            <flux:text class="whitespace-pre-wrap">{{ $document->summary ?: ($analysis['summary'] ?? __('No summary recorded.')) }}</flux:text>
        </div>
        @if (! empty($analysis['warnings']))
            <div role="status" class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
                <strong>{{ __('Review notes and warnings') }}</strong>
                <ul class="mt-2 list-disc space-y-1 pl-5">
                    @foreach ($analysis['warnings'] as $warning)
                        <li>{{ $warning }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($isLegislation)
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                @foreach (['title' => __('Extracted title'), 'short_title' => __('Short title'), 'legislation_type' => __('Instrument type'), 'relationship_type' => __('Role in review'), 'date_enacted' => __('Date enacted')] as $key => $label)
                    @if (filled($analysis[$key] ?? null))
                        <div><dt class="text-zinc-500 dark:text-zinc-400">{{ $label }}</dt><dd class="mt-1">{{ $analysis[$key] }}</dd></div>
                    @endif
                @endforeach
            </dl>
        @endif
        @foreach ($linkedLegislation as $law)
            <div class="space-y-1">
                <flux:heading>{{ $law->title }}</flux:heading>
                <flux:text>{{ $law->short_title }} @if ($law->date_enacted) · {{ __('Enacted :date', ['date' => $law->date_enacted->format('j M Y')]) }} @endif</flux:text>
            </div>
        @endforeach
    </flux:card>

    @foreach (['key_themes' => __('Key themes'), 'important_dates' => __('Important dates'), 'notable_excerpts' => __('Notable excerpts'), 'stakeholder_suggestions' => __('Suggested stakeholders')] as $key => $label)
        @if (! empty($analysis[$key]))
            <flux:card class="space-y-3">
                <flux:heading>{{ $label }}</flux:heading>
                <ul class="list-disc space-y-2 pl-5 text-sm text-zinc-700 dark:text-zinc-200">
                    @foreach ($analysis[$key] as $item)
                        <li class="whitespace-pre-wrap">{{ is_array($item) ? collect($item)->filter(fn ($value) => is_scalar($value))->implode(' · ') : $item }}</li>
                    @endforeach
                </ul>
            </flux:card>
        @endif
    @endforeach

    @foreach (($analysis['scrutiny_preparation'] ?? []) as $key => $items)
        @if ($items !== [])
            <flux:card class="space-y-3">
                <flux:heading>{{ \Illuminate\Support\Str::headline($key) }}</flux:heading>
                @foreach ($items as $item)
                    <div class="space-y-1 text-sm">
                        <p class="font-medium">{{ $item['title'] ?? '' }}</p>
                        <flux:text>{{ $item['detail'] ?? '' }} {{ $item['timing'] ?? '' }}</flux:text>
                        @if (! empty($item['source_text']))
                            <blockquote class="border-l-2 border-violet-300 pl-3 text-zinc-600 dark:text-zinc-300">{{ $item['source_text'] }}</blockquote>
                        @endif
                    </div>
                @endforeach
            </flux:card>
        @endif
    @endforeach

    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Extracted document text') }}</flux:heading>
        <flux:input wire:model.live.debounce.300ms="search" type="search" :label="__('Search within document')" />
        <flux:text>{{ __(':count extracted sections. Section numbers refer to extracted text, not original page numbers.', ['count' => $chunkCount]) }}</flux:text>
        <flux:text>{{ __('Read the extracted text in source order. Use the original document to check formatting, images, and extraction accuracy.') }}</flux:text>
        @forelse ($textChunks as $chunk)
            <section id="section-{{ $chunk->chunk_index + 1 }}" wire:key="document-chunk-{{ $chunk->id }}" class="space-y-2 border-b pb-4">
                <flux:heading size="sm">{{ __('Section :number', ['number' => $chunk->chunk_index + 1]) }}</flux:heading>
                <div class="whitespace-pre-wrap break-words text-sm leading-7 text-zinc-800 dark:text-zinc-200">{{ $chunk->content }}</div>
            </section>
        @empty
            <flux:text>{{ $search !== '' ? __('No sections match your search. Clear the search to read all extracted text.') : __('No extracted text is available yet. The assistant cannot review this document in full until text extraction succeeds.') }}</flux:text>
        @endforelse
        {{ $textChunks->links() }}
    </flux:card>
    @can('update', $review)
        @if ($findings->isNotEmpty())
            <flux:card class="space-y-4">
                <flux:heading>{{ __('Link a reviewed passage to a finding') }}</flux:heading>
                <flux:text>{{ __('Check the original, copy the exact passage, and explain what it supports or contradicts. Linking a passage does not verify the whole finding.') }}</flux:text>
                <flux:select wire:model="citationFindingId" :label="__('Finding')"><option value="">{{ __('Choose a finding') }}</option>@foreach ($findings as $finding)<option value="{{ $finding->id }}">{{ $finding->title }}</option>@endforeach</flux:select>
                <flux:textarea wire:model="citationPassage" :label="__('Exact source passage')" rows="4" />
                <flux:select wire:model="citationRelationship" :label="__('Relationship to finding')"><option value="supports">Supports</option><option value="contradicts">Contradicts</option><option value="context">Context only</option></flux:select>
                <flux:textarea wire:model="citationNote" :label="__('Reviewer note and limitations')" rows="3" />
                <flux:button wire:click="saveCitation" variant="primary">{{ __('Save reviewed passage') }}</flux:button>
                <flux:link :href="route('pls.reviews.analysis', $review)" wire:navigate>{{ __('Return to findings') }}</flux:link>
            </flux:card>
        @endif
    @endcan
</div>
