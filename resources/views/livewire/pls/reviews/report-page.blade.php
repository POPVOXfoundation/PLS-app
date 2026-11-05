<div class="space-y-6" @if(in_array($report->generation_status, ['queued', 'generating'])) wire:poll.5s @endif>
    <flux:link :href="route('pls.reviews.reports', $review)" wire:navigate>{{ __('Back to reports') }}</flux:link>
    <flux:heading size="xl">{{ $title }}</flux:heading>
    <div class="flex flex-wrap gap-3">
        <flux:badge>{{ \Illuminate\Support\Str::headline($report->status->value) }}</flux:badge>
        @if(filled($content))
        @foreach(['pdf' => 'Download PDF', 'docx' => 'Download Word', 'md' => 'Download Markdown'] as $format => $label)
            <flux:button :href="route('pls.reviews.reports.export', [$review, $report, 'format' => $format, 'version' => $version])" icon="arrow-down-tray">{{ __($label) }}</flux:button>
        @endforeach
        @endif
        @can('update', $review)<flux:button variant="primary" wire:click="editDraft">{{ __('Edit current draft') }}</flux:button>@endcan
        @if ($report->document)<flux:button :href="route('pls.reviews.documents.show', [$review, $report->document])" wire:navigate>{{ __('Open linked document and original') }}</flux:button>@endif
    </div>
    @if ($report->generation_status)
        <flux:card role="status" aria-live="polite">
            <flux:heading>{{ __('Report generation: :status', ['status' => $report->generation_status]) }}</flux:heading>
            @if(in_array($report->generation_status, ['queued', 'generating']))<flux:text>{{ __('The draft will appear here when ready. You can leave and return.') }}</flux:text>@endif
            @if($report->generation_error)<flux:text>{{ $report->generation_error }}</flux:text>@endif
            @if($report->generation_status === 'failed' || (in_array($report->generation_status, ['queued', 'generating']) && $report->generation_started_at?->lt(now()->subMinutes(10))))
                <flux:text>{{ __('If this is taking longer than expected, ask the administrator to check the queue worker.') }}</flux:text>
                @can('update', $review)<flux:button wire:click="regenerate" wire:confirm="Generate a replacement draft? Existing content remains in version history.">{{ __('Retry generation') }}</flux:button>@endcan
            @endif
        </flux:card>
    @endif
    @if($editing)
        <flux:card class="space-y-4">
            <flux:heading>{{ __('Edit report sections') }}</flux:heading>
            <flux:text>{{ __('Changes save after you pause typing. Downloads contain the selected saved version. Keep Markdown headings to preserve sections.') }}</flux:text>
            <p role="status" class="text-sm">{{ $saveStatus }} <span wire:loading wire:target="sections,saveDraft">{{ __('Saving…') }}</span></p>
            <flux:error name="sections" />
            @foreach($sections as $index => $section)
                <div class="space-y-2" wire:key="section-{{ $index }}">
                    <flux:textarea :label="\App\Support\Reports\ReportDrafts::label($section, $index)" wire:model.live.debounce.1500ms="sections.{{ $index }}" rows="10" />
                    <flux:button size="sm" wire:click="regenerate({{ $index }})" wire:confirm="Regenerate only this section? Your saved version will remain in history.">{{ __('Regenerate this section') }}</flux:button>
                </div>
            @endforeach
            <flux:button wire:click="saveDraft" variant="primary">{{ __('Save draft now') }}</flux:button>
        </flux:card>
    @endif
    @if ($versions !== [])
        <flux:select wire:model.live="version" :label="__('Version history')">
            <option value="0">{{ __('Current version') }}</option>
            @foreach ($versions as $index => $revision)<option value="{{ $index + 1 }}">{{ __('Version :number', ['number' => $index + 1]) }} · {{ $revision['saved_at'] }}</option>@endforeach
        </flux:select>
    @endif
    <flux:card>
        @if (filled($content))
            <article class="pls-prose prose max-w-none dark:prose-invert">{!! \Illuminate\Support\Str::markdown($content, ['html_input' => 'strip', 'allow_unsafe_links' => false]) !!}</article>
        @else
            <flux:heading>{{ __('Report record only — no draft text saved') }}</flux:heading>
            <flux:text class="mt-3">{{ __('This record tracks a report, but does not contain its text. Open the linked document, or return to Reports and edit this record to add content. Earlier assistant drafts were not automatically saved here.') }}</flux:text>
        @endif
    </flux:card>
</div>
