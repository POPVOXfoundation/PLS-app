<div
    x-data="{
        deleteConfirmation: { id: null, title: '', noun: '' },
        setDeleteConfirmation(id, title, noun) {
            this.deleteConfirmation = { id, title, noun };
        },
        resetDeleteConfirmation() {
            this.deleteConfirmation = { id: null, title: '', noun: '' };
        }
    }"
    class="space-y-6"
>
    <flux:card class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
            <flux:modal.trigger name="add-document">
                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add') }}</flux:button>
            </flux:modal.trigger>
        </div>

        @if ($review->documents->isEmpty())
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No documents linked to this review yet.') }}
            </flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Title') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('MIME') }}</flux:table.column>
                    <flux:table.column>{{ __('Size') }}</flux:table.column>
                    <flux:table.column align="end"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($review->documents as $document)
                        <flux:table.row :key="$document->id">
                            <flux:table.cell variant="strong">
                                {{ $document->title }}
                                @if ($document->summary)
                                    <flux:text class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ \Illuminate\Support\Str::limit($document->summary, 80) }}</flux:text>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ $this->documentTypeLabel($document->document_type) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $document->mime_type }}</flux:table.cell>
                            <flux:table.cell>{{ $document->fileSizeLabel() }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:modal.trigger name="edit-document">
                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingDocument({{ $document->id }})" />
                                    </flux:modal.trigger>

                                    <flux:modal.trigger name="confirm-document-delete">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            :loading="false"
                                            x-on:click="setDeleteConfirmation({{ $document->id }}, @js($document->title), @js(__('document')))"
                                        />
                                    </flux:modal.trigger>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal name="add-document" class="md:w-[32rem]">
        <form wire:submit="storeDocument" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add document') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Upload the working file now or record an existing storage path and metadata.') }}</flux:text>
            </div>

            <flux:input wire:model="documentTitle" :invalid="$errors->has('documentTitle')" :label="__('Title')" />

            <flux:field>
                <flux:label>{{ __('Upload file') }}</flux:label>
                <input
                    type="file"
                    wire:model="documentUpload"
                    class="block w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-200"
                />
                <flux:error name="documentUpload" />
                <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Optional if the file already exists in storage and you want to reference its path directly.') }}
                </flux:text>
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="documentType" :invalid="$errors->has('documentType')" :label="__('Type')">
                    @foreach ($documentTypes as $type)
                        <flux:select.option :value="$type->value">{{ $this->documentTypeLabel($type) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="documentStoragePath" :invalid="$errors->has('documentStoragePath')" :label="__('Storage path')" placeholder="pls/reviews/12/documents/file.pdf" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="documentMimeType" :invalid="$errors->has('documentMimeType')" :label="__('MIME type')" />
                <flux:input wire:model="documentFileSize" :invalid="$errors->has('documentFileSize')" :label="__('File size (bytes)')" type="number" min="1" />
            </div>

            <flux:textarea wire:model="documentSummary" :invalid="$errors->has('documentSummary')" :label="__('Summary')" rows="3" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="edit-document" class="md:w-[32rem]">
        <form wire:submit="updateDocument" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit document') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Update document metadata or replace the stored file for this review record.') }}</flux:text>
            </div>

            <flux:input wire:model="documentTitle" :invalid="$errors->has('documentTitle')" :label="__('Title')" />

            <flux:field>
                <flux:label>{{ __('Replace file') }}</flux:label>
                <input
                    type="file"
                    wire:model="documentUpload"
                    class="block w-full rounded-xl border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-700 file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium file:text-zinc-700 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:file:bg-zinc-800 dark:file:text-zinc-200"
                />
                <flux:error name="documentUpload" />
                <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Leave blank to keep the current stored file and metadata path.') }}
                </flux:text>
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="documentType" :invalid="$errors->has('documentType')" :label="__('Type')">
                    @foreach ($documentTypes as $type)
                        <flux:select.option :value="$type->value">{{ $this->documentTypeLabel($type) }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="documentStoragePath" :invalid="$errors->has('documentStoragePath')" :label="__('Storage path')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="documentMimeType" :invalid="$errors->has('documentMimeType')" :label="__('MIME type')" />
                <flux:input wire:model="documentFileSize" :invalid="$errors->has('documentFileSize')" :label="__('File size (bytes)')" type="number" min="1" />
            </div>

            <flux:textarea wire:model="documentSummary" :invalid="$errors->has('documentSummary')" :label="__('Summary')" rows="3" />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
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
                        x-text="`${@js(__('This will permanently remove'))} “${deleteConfirmation.title}” ${@js(__('from the review workspace.'))}`"
                    ></span>
                    <span x-show="! deleteConfirmation.title">{{ __('This will permanently remove the selected item from the review workspace.') }}</span>
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
                        :loading="false"
                        x-on:click="$wire.confirmDeletion(deleteConfirmation.id); resetDeleteConfirmation()"
                        x-bind:disabled="! deleteConfirmation.id"
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
