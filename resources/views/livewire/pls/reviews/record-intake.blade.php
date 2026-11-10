<div class="space-y-4">
    <flux:heading size="lg">{{ __('Add to record') }}</flux:heading>
    <flux:text>{{ __('Add files, record a source link, or upload a metadata spreadsheet. Only the first worksheet is read; use up to 250 rows and 20 files. Dates use YYYY-MM-DD.') }}</flux:text>
    <flux:input type="file" wire:model="files" multiple :label="__('Source files (PDF, DOCX, TXT, MD)')" />
    <flux:input type="file" wire:model="spreadsheet" accept=".csv,.xlsx" :label="__('Metadata spreadsheet (CSV or XLSX)')" />
    <flux:link :href="route('pls.reviews.record.export', [$review, 'format' => 'csv', 'template' => 1])">{{ __('Download import template') }}</flux:link>
    <flux:input wire:model="title" :label="__('Title for source link')" />
    <flux:input wire:model="url" type="url" :label="__('Original source URL')" />
    <flux:select wire:model="type" :label="__('Document type')">
        @foreach ($types as $type)
            <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
        @endforeach
    </flux:select>
    <flux:button wire:click="previewImport">{{ __('Preview additions') }}</flux:button>
    <flux:error name="files" />
    <flux:error name="spreadsheet" />
    @if ($preview)
        @foreach ($preview as $row)
            <div wire:key="intake-row-{{ $row['row'] }}" class="space-y-1 rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                <flux:text>{{ $row['data']['title'] }} · {{ $row['data']['filename'] ?: $row['data']['url'] }}</flux:text>
                @if ($row['duplicate']) <flux:badge>{{ __('Duplicate source') }}</flux:badge> @endif
                @foreach ($row['errors'] as $error) <flux:text>{{ $error }}</flux:text> @endforeach
            </div>
        @endforeach
        <flux:checkbox wire:model="allowDuplicates" :label="__('I confirm that duplicate sources in this batch are intentional')" />
        <flux:button wire:click="import" variant="primary">{{ __('Confirm additions') }}</flux:button>
    @endif
    <flux:text>{{ $notice }}</flux:text>
</div>
