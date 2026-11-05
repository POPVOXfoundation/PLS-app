<div class="space-y-6" wire:poll.10s>
    <flux:heading size="xl">{{ __('Documents & Sources') }}</flux:heading>
    <flux:text>{{ __('Legislation establishes the legal baseline. Context explains the issue. Submissions record contributions. Evidence is material linked to a particular claim and still requires human judgment.') }}</flux:text>
    @can('update', $review)
        <details class="rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
            <summary>{{ __('Add to record') }}</summary>
            <livewire:pls.reviews.record-intake :review="$review" />
        </details>
    @endcan
    <div class="flex flex-wrap gap-3">
        <flux:button :href="route('pls.reviews.record.export', [$review, 'format' => 'xlsx'])">{{ __('Export complete register to Excel') }}</flux:button>
        <flux:button :href="route('pls.reviews.record.export', [$review, 'format' => 'csv'])">{{ __('Export complete register to CSV') }}</flux:button>
    </div>
    <flux:input wire:model.live.debounce.300ms="search" :label="__('Search source titles')" />
    <flux:select wire:model.live="type" :label="__('Filter by type')">
        <flux:select.option value="">{{ __('All types') }}</flux:select.option>
        @foreach ($types as $type) <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option> @endforeach
    </flux:select>
    @forelse ($documents as $document)
        <flux:card :wire:key="'register-source-'.$document->id" class="space-y-3">
            <flux:link :href="route('pls.reviews.documents.show', [$review, $document])" wire:navigate>{{ $document->title }}</flux:link>
            <flux:badge>{{ $document->roleLabel() }}</flux:badge>
            <flux:badge>{{ $document->processingLabel() }}</flux:badge>
            <flux:badge>{{ $document->teamCheckLabel() }}</flux:badge>
            <flux:badge>{{ data_get($document->metadata, 'source_review.excluded') ? __('Excluded from assistant/report inputs') : __('Included in review record') }}</flux:badge>
            <flux:text>{{ $document->summary }}</flux:text>
            @foreach ($document->submissions as $submission) <flux:text>{{ __('Submission from :name', ['name' => $submission->stakeholder?->name ?? __('Unrecorded')]) }}</flux:text> @endforeach
            @foreach ($document->consultationMaterials as $material) <flux:text>{{ __('Consultation: :title', ['title' => $material->consultation?->title ?? __('Unrecorded')]) }}</flux:text> @endforeach
        </flux:card>
    @empty
        <flux:text>{{ __('No sources match these filters.') }}</flux:text>
    @endforelse
    {{ $documents->links() }}
</div>
