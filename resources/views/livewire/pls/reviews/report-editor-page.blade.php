<div class="space-y-6" @if (in_array($report->generation_status, ['queued', 'generating'])) wire:poll.5s @endif>
    <flux:heading size="xl">{{ $version->title }}</flux:heading>
    <flux:text>{{ __('Working drafts require human review before publication.') }}</flux:text>
    @if ($report->generation_status)
        <flux:badge>{{ \Illuminate\Support\Str::headline($report->generation_status) }}</flux:badge>
        <flux:text>{{ $report->generation_error }}</flux:text>
        @if ($report->generation_status === 'queued')
            <flux:text>{{ __('Waiting for a report worker. You can leave this page and return to the same draft.') }}</flux:text>
        @endif
    @endif
    <flux:error name="generation" />
    <div class="flex flex-wrap gap-2">
        @foreach (['pdf' => 'PDF', 'docx' => 'Word', 'md' => 'Markdown'] as $format => $label)
            <flux:button :href="route('pls.reviews.reports.download', [$review, $report, 'format' => $format, ...($selectedRevision < 0 ? [] : ['revision' => $selectedRevision])])">{{ __('Download :format', ['format' => $label]) }}</flux:button>
        @endforeach
        @can('update', $review)
            <flux:button wire:click="edit">{{ __('Edit current draft') }}</flux:button>
            <flux:button wire:click="regenerate" wire:confirm="{{ __('Generate a replacement from the current record? Saved versions will remain available.') }}">{{ __('Generate again / retry') }}</flux:button>
        @endcan
    </div>
    @can('update', $review)
        @if (! $editing && $selectedRevision < 0 && count($currentSections) > 0)
            <div class="space-y-3">
                <flux:select wire:model="sectionToRegenerate" :label="__('Generate one section again')">
                    @foreach ($currentSections as $index => $section)
                        <flux:select.option :value="$index">{{ __('Section :number', ['number' => $index + 1]) }} · {{ \Illuminate\Support\Str::limit(strtok($section, "\n"), 90) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button wire:click="regenerateSection({{ $report->revision }})" wire:confirm="{{ __('Replace only this section from the current record? Saved versions will remain available.') }}">{{ __('Generate selected section') }}</flux:button>
            </div>
        @endif
    @endcan
    <flux:select wire:model.live="selectedRevision" :label="__('Saved version')" :disabled="$editing">
        <flux:select.option value="-1">{{ __('Current draft') }}</flux:select.option>
        @foreach ($versions as $saved)
            <flux:select.option :value="$saved->revision">{{ __('Revision :number', ['number' => $saved->revision]) }} · {{ $saved->created_at->toDayDateTimeString() }}</flux:select.option>
        @endforeach
    </flux:select>
    @if ($editing)
        <form wire:submit="save" class="space-y-4">
            <flux:text>{{ $saveStatus }}</flux:text>
            <flux:error name="sections" />
            @foreach ($sections as $index => $section)
                <flux:textarea :wire:key="'report-section-'.$index" wire:model="sections.{{ $index }}" :label="__('Section :number', ['number' => $index + 1])" rows="12" />
            @endforeach
            <flux:button type="submit" variant="primary">{{ __('Save report') }}</flux:button>
            <flux:button wire:click="finishEditing" wire:confirm="{{ __('Leave the editor? Any unsaved edits will not be kept.') }}">{{ __('Close editor') }}</flux:button>
        </form>
    @else
        <article class="report-content max-w-none">{!! $contentHtml !!}</article>
        @if (blank($version->content))
            <flux:text>{{ __('This record has no saved report text yet.') }}</flux:text>
        @endif
    @endif
</div>
