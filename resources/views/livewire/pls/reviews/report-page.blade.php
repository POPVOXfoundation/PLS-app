<div class="space-y-6">
    <flux:link :href="route('pls.reviews.reports', $review)" wire:navigate>{{ __('Back to reports') }}</flux:link>
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <div class="flex flex-wrap gap-3">
        <flux:badge>{{ \Illuminate\Support\Str::headline($report->status->value) }}</flux:badge>
        @if (filled($report->content))<flux:button :href="route('pls.reviews.reports.export', [$review, $report])" icon="arrow-down-tray">{{ __('Download current draft (Markdown)') }}</flux:button>@endif
        @if ($report->document)<flux:button :href="route('pls.reviews.documents.show', [$review, $report->document])" wire:navigate>{{ __('Open linked document and original') }}</flux:button>@endif
    </div>
    @if ($versions !== [])
        <flux:select wire:model.live="version" :label="__('Version history')">
            <option value="0">{{ __('Current version') }}</option>
            @foreach ($versions as $index => $revision)<option value="{{ $index + 1 }}">{{ __('Version :number', ['number' => $index + 1]) }} · {{ $revision['saved_at'] }}</option>@endforeach
        </flux:select>
    @endif
    <flux:card>
        @if (filled($content))
            <article class="prose max-w-none dark:prose-invert">{!! \Illuminate\Support\Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</article>
        @else
            <flux:heading>{{ __('Report record only — no draft text saved') }}</flux:heading>
            <flux:text class="mt-3">{{ __('This record tracks a report, but does not contain its text. Open the linked document, or return to Reports and edit this record to add content. Earlier assistant drafts were not automatically saved here.') }}</flux:text>
        @endif
    </flux:card>
</div>
