<div class="space-y-6" @if (in_array(data_get($document->metadata, 'extraction.status'), ['queued', 'processing'], true)) wire:poll.10s @endif>
    <div class="space-y-3">
        <flux:heading size="xl" level="1">{{ $document->title }}</flux:heading>
        <flux:text>{{ $document->summary }}</flux:text>
        <flux:text>{{ $document->pageCoverageLabel() }}</flux:text>
        @if ($document->originalUrl())
            <flux:button :href="$document->originalUrl()">{{ __('Open original') }}</flux:button>
        @endif
        @foreach ($warnings as $warning)
            <flux:text>{{ $warning }}</flux:text>
        @endforeach
        @foreach ((array) data_get($document->metadata, 'extraction.page_coverage.warnings', []) as $warning)
            <flux:text>{{ __('Extraction warning: :code. Pages: :pages', ['code' => $warning['ErrorCode'] ?? __('Unspecified'), 'pages' => implode(', ', $warning['Pages'] ?? [])]) }}</flux:text>
        @endforeach
    </div>

    @can('update', $review)
        <section class="space-y-3">
            <flux:heading size="lg">{{ __('Processing and team check') }}</flux:heading>
            <flux:badge>{{ $document->processingLabel() }}</flux:badge>
            <flux:badge>{{ $document->teamCheckLabel() }}</flux:badge>
            <flux:text>{{ data_get($document->metadata, 'extraction.error') }}</flux:text>
            <flux:error name="processing" />
            @if (blank($document->storage_path))
                <flux:input type="file" wire:model="sourceUpload" :label="__('Attach original file to this source')" />
                <flux:button wire:click="attachOriginal">{{ __('Attach and process') }}</flux:button>
            @else
                <flux:button wire:click="retryProcessing">{{ __('Retry processing') }}</flux:button>
            @endif
            <flux:button wire:click="markChecked">{{ __('Mark team checked') }}</flux:button>
            <flux:button wire:click="setExcluded({{ data_get($document->metadata, 'source_review.excluded') ? 'false' : 'true' }})">{{ data_get($document->metadata, 'source_review.excluded') ? __('Include in assistant/report inputs') : __('Exclude from assistant/report inputs') }}</flux:button>
            <flux:text>{{ __('A team check records a human review; it does not verify every claim.') }}</flux:text>
        </section>
    @endcan

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Source identity') }}</flux:heading>
        <flux:text>{{ __('Classification describes this source. Uploading or extracting text does not verify its claims or legal status.') }}</flux:text>
        @if ($document->document_type === \App\Domain\Documents\Enums\DocumentType::LegislationText)
        @can('update', $review)
            <form wire:submit="saveClassification" class="space-y-3">
                <flux:select wire:model="classification" :label="__('Document classification')">
                    @foreach ($sourceTypes as $type)
                        <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button type="submit">{{ __('Save classification') }}</flux:button>
            </form>
        @else
            <flux:text>{{ \Illuminate\Support\Str::headline($classification) }}</flux:text>
        @endcan
        @endif
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Related records') }}</flux:heading>
        @foreach ($document->submissions as $submission)
            <flux:text>{{ __('Submission from :name', ['name' => $submission->stakeholder?->name ?? __('Unrecorded stakeholder')]) }}</flux:text>
        @endforeach
        @foreach ($document->consultationMaterials as $material)
            <flux:text>{{ __('Consultation: :title', ['title' => $material->consultation?->title ?? __('Unrecorded consultation')]) }}</flux:text>
        @endforeach
    </section>

    <section class="space-y-3">
        <flux:heading size="lg" level="2">{{ __('Extracted text') }}</flux:heading>
        <flux:text>{{ __('Text blocks follow source order. Block numbers are not original page numbers. Check quotations against the original.') }}</flux:text>
        @forelse ($chunks as $chunk)
            <div wire:key="source-chunk-{{ $chunk->id }}" class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <flux:heading>{{ __('Text block :number', ['number' => $chunk->chunk_index + 1]) }}</flux:heading>
                <flux:text class="whitespace-pre-wrap break-words">{{ $chunk->content }}</flux:text>
            </div>
        @empty
            <flux:text>{{ __('No extracted text is available for the assistant to review.') }}</flux:text>
        @endforelse
        {{ $chunks->links() }}
    </section>

    <section class="space-y-4">
        <flux:heading size="lg" level="2">{{ __('Finding passages') }}</flux:heading>
        <flux:text>{{ __('These links record a reviewer’s assessment of a passage, not independent verification of the finding.') }}</flux:text>
        @foreach ($citations as $citation)
            <div wire:key="source-citation-{{ $citation->id }}" class="space-y-2 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <flux:heading>{{ $citation->finding->title }}</flux:heading>
                <flux:badge>{{ __($citation->relationship) }}</flux:badge>
                <flux:badge>{{ $citation->isCurrent() ? __('Passage checked against current text') : __('Needs rechecking: finding or source changed') }}</flux:badge>
                <flux:text>{{ $citation->passage }}</flux:text>
                <flux:text>{{ $citation->note }}</flux:text>
            </div>
        @endforeach
        @can('update', $review)
            <form wire:submit="linkPassage" class="space-y-4">
                <flux:select wire:model="findingId" :label="__('Finding')" :placeholder="__('Choose a finding')">
                    @foreach ($findings as $finding)
                        <flux:select.option :value="$finding->id">{{ $finding->title }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="relationship" :label="__('Relationship')">
                    <flux:select.option value="supports">{{ __('Supports') }}</flux:select.option>
                    <flux:select.option value="contradicts">{{ __('Contradicts') }}</flux:select.option>
                    <flux:select.option value="context">{{ __('Context only') }}</flux:select.option>
                </flux:select>
                <flux:textarea wire:model="passage" :label="__('Exact passage from this source')" rows="5" />
                <flux:textarea wire:model="note" :label="__('Reviewer note')" rows="3" />
                <flux:button type="submit" variant="primary">{{ __('Link passage') }}</flux:button>
            </form>
        @endcan
    </section>
</div>
