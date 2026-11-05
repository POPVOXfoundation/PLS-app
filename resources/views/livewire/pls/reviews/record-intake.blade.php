<flux:card id="add-to-record" class="space-y-4">
    <flux:heading size="lg">{{ __('Add to record') }}</flux:heading>
    <flux:text>{{ __('Add documents, source links and spreadsheet rows here. All files appear in one register, including legislation, templates and stakeholder submissions. Adding a source does not verify its claims.') }}</flux:text>
    @can('update', $review)
    <flux:input type="file" wire:model="files" multiple accept=".pdf,.docx,.txt,.md" :label="__('Files (up to 20, 50 MB each)')" />
    <flux:select wire:model="type" :label="__('Source type (unless specified in spreadsheet)')">@foreach(\App\Domain\Documents\Enums\DocumentType::cases() as $case)<option value="{{ $case->value }}">{{ \Illuminate\Support\Str::headline($case->value) }}</option>@endforeach</flux:select>
    <details class="rounded border p-3"><summary class="cursor-pointer font-medium">{{ __('Add a link or import spreadsheet metadata') }}</summary>
        <div class="mt-3 space-y-3">
            <flux:input wire:model="url" type="url" :label="__('Original source URL (optional)')" />
            <flux:input wire:model="title" :label="__('Title for this link')" />
            <flux:input wire:model="spreadsheet" type="file" accept=".csv,.xlsx" :label="__('Spreadsheet (CSV or XLSX, up to 250 rows)')" />
            <flux:link :href="route('pls.reviews.documents.register', [$review, 'template' => 1])">{{ __('Download spreadsheet template (CSV)') }}</flux:link>
            <flux:text>{{ __('One row per source, on the first worksheet. Match filename exactly to a selected file, or supply a URL. Use YYYY-MM-DD dates and source types from the list above. For consultation submissions, the stakeholder column links the contribution to a person or organisation. New names are added as unclassified stakeholders for the team to check.') }}</flux:text>
            <p class="text-xs">{{ implode(', ', array_column(\App\Domain\Documents\Enums\DocumentType::cases(), 'value')) }}</p>
        </div>
    </details>
    <flux:error name="files" /><flux:error name="files.*" /><flux:error name="spreadsheet" /><flux:error name="url" />
    <flux:button wire:click="previewImport" wire:loading.attr="disabled" wire:target="files,spreadsheet,previewImport,import">{{ __('Preview additions') }}</flux:button>
    <p wire:loading wire:target="files,spreadsheet,previewImport,import" role="status">{{ __('Preparing your records…') }}</p>
    @if($preview !== [])
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">{{ __('Row / title') }}</th><th class="p-2">{{ __('File match / link') }}</th><th class="p-2">{{ __('Checks') }}</th></tr></thead><tbody>
        @foreach($preview as $item)<tr class="border-t"><td class="p-2">{{ $item['row'] }} · {{ $item['data']['title'] }}</td><td class="p-2">{{ $item['file_index'] !== null ? $item['data']['filename'] : __('Source link only — text not reviewed') }}</td><td class="p-2">{{ implode(' ', $item['errors']) }} @if($item['duplicate'])<strong>{{ __('Possible duplicate') }}</strong>@elseif($item['errors'] === []){{ __('Ready to add') }}@endif</td></tr>@endforeach
        </tbody></table></div>
        @if(collect($preview)->contains('duplicate', true))<flux:checkbox wire:model="allowDuplicates" :label="__('I checked the possible duplicates and want to add them')" />@endif
        <flux:button variant="primary" wire:click="import" wire:loading.attr="disabled">{{ __('Confirm and add to record') }}</flux:button>
    @endif
    @endcan
    @if($notice)<p role="status" class="text-sm text-emerald-700">{{ $notice }}</p>@endif
</flux:card>
