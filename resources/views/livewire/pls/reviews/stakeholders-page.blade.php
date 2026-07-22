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
    class="space-y-8"
>
    <flux:card class="space-y-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="space-y-2">
                <flux:heading size="lg">{{ __('Suggestions from legislation and evidence') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('PLSAssist can suggest stakeholders and implementing agencies named or implied by uploaded legislation and evidence. Review each suggestion before adding it to the directory.') }}
                </flux:text>
            </div>

            @if ($suggestedStakeholders->isNotEmpty())
                <flux:badge size="sm">{{ __(':count suggestions', ['count' => $suggestedStakeholders->count()]) }}</flux:badge>
            @endif
        </div>

        @if ($suggestedStakeholders->isEmpty())
            <div class="rounded-xl border border-dashed border-zinc-300 bg-zinc-50/70 px-4 py-4 dark:border-zinc-700 dark:bg-zinc-900/40">
                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                    {{ __('No stakeholder suggestions are ready yet. New or retried legislation and evidence uploads will surface suggestions here when PLSAssist can identify relevant actors from the source text.') }}
                </flux:text>
            </div>
        @else
            <div class="grid gap-3 lg:grid-cols-2">
                @foreach ($suggestedStakeholders as $suggestion)
                    <section class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <flux:heading size="base" class="break-words">{{ $suggestion['name'] }}</flux:heading>
                                    <flux:badge size="sm" :color="$suggestion['kind'] === 'implementing_agency' ? 'sky' : 'zinc'">
                                        {{ $suggestion['kind'] === 'implementing_agency' ? __('Implementing agency') : __('Stakeholder') }}
                                    </flux:badge>
                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($suggestion['category']) }}</flux:badge>
                                </div>

                                @if ($suggestion['rationale'] !== '')
                                    <flux:text class="text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ $suggestion['rationale'] }}</flux:text>
                                @endif

                                <div class="space-y-1 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($suggestion['source'] !== '')
                                        <div>{{ __('Source phrase: :source', ['source' => $suggestion['source']]) }}</div>
                                    @endif
                                    @if ($suggestion['source_title'] !== '')
                                        <div>{{ __('From: :title', ['title' => $suggestion['source_title']]) }}</div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 flex flex-wrap justify-end gap-2">
                            @if ($suggestion['kind'] === 'implementing_agency')
                                <flux:button size="sm" variant="primary" wire:click="prepareSuggestedImplementingAgency(@js($suggestion['id']))">
                                    {{ __('Review and add') }}
                                </flux:button>
                            @else
                                <flux:button size="sm" variant="primary" wire:click="prepareSuggestedStakeholder(@js($suggestion['id']))">
                                    {{ __('Review and add') }}
                                </flux:button>
                            @endif

                            <flux:button size="sm" variant="ghost" wire:click="dismissSuggestedStakeholder(@js($suggestion['id']))">
                                {{ __('Dismiss') }}
                            </flux:button>
                        </div>
                    </section>
                @endforeach
            </div>
        @endif
    </flux:card>

    <flux:card class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Stakeholder directory') }}</flux:heading>

            <div class="flex items-center gap-3">
                @if ($review->stakeholders->isNotEmpty())
                    <flux:select wire:model.live="stakeholderTypeFilter" size="sm" class="max-w-48">
                        <flux:select.option value="all">{{ __('All types') }}</flux:select.option>
                        @foreach ($stakeholderTypes as $stakeholderTypeOption)
                            <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>
                @endif

                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareStakeholderCreate">{{ __('Add') }}</flux:button>
            </div>
        </div>

        @if ($review->stakeholders->isEmpty())
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No stakeholders recorded yet. Add the ministries, institutions, experts, and civil society actors the review team expects to engage.') }}
            </flux:text>
        @elseif ($filteredStakeholders->isEmpty())
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No stakeholders match this filter.') }}
                <flux:button variant="ghost" size="sm" wire:click="clearStakeholderFilter">{{ __('Clear filter') }}</flux:button>
            </flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                    <flux:table.column>{{ __('Organization') }}</flux:table.column>
                    <flux:table.column>{{ __('Contact') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($filteredStakeholders->sortBy('name') as $stakeholder)
                        @php
                            $contactDetails = $stakeholder->contact_details ?? [];
                        @endphp

                        <flux:table.row :key="$stakeholder->id">
                            <flux:table.cell variant="strong">{{ $stakeholder->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($stakeholder->stakeholder_type->value) }}</flux:badge>
                            </flux:table.cell>
                            <flux:table.cell>{{ $contactDetails['organization'] ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                <div class="space-y-0.5 text-xs text-zinc-500 dark:text-zinc-400">
                                    @if ($contactDetails['email'] ?? null)
                                        <div>{{ $contactDetails['email'] }}</div>
                                    @endif
                                    @if ($contactDetails['phone'] ?? null)
                                        <div>{{ $contactDetails['phone'] }}</div>
                                    @endif
                                    @if (! ($contactDetails['email'] ?? null) && ! ($contactDetails['phone'] ?? null))
                                        <div>—</div>
                                    @endif
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingStakeholder({{ $stakeholder->id }})" />

                                    <flux:modal.trigger name="confirm-stakeholder-delete">
                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="trash"
                                            x-on:click="setDeleteConfirmation({{ $stakeholder->id }}, @js($stakeholder->name), @js(__('stakeholder')))"
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

    <flux:card class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Implementing agencies') }}</flux:heading>

            <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareImplementingAgencyCreate">{{ __('Add') }}</flux:button>
        </div>

        @if ($review->implementingAgencies->isEmpty())
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('No implementing agencies recorded yet.') }}
            </flux:text>
        @else
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Type') }}</flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @foreach ($review->implementingAgencies as $agency)
                        <flux:table.row :key="$agency->id">
                            <flux:table.cell variant="strong">{{ $agency->name }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($agency->agency_type->value) }}</flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        @endif
    </flux:card>

    <flux:modal wire:model.self="showAddStakeholderModal" class="md:w-[36rem]">
        <form wire:submit="storeStakeholder" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add stakeholder') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Capture the organizations, experts, and public-interest actors who should inform this review.') }}</flux:text>
            </div>

            <flux:input wire:model="stakeholderName" :invalid="$errors->has('stakeholderName')" :label="__('Name')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="stakeholderType" :invalid="$errors->has('stakeholderType')" :label="__('Type')">
                    @foreach ($stakeholderTypes as $stakeholderTypeOption)
                        <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="stakeholderOrganization" :invalid="$errors->has('stakeholderOrganization')" :label="__('Organization')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="stakeholderEmail" :invalid="$errors->has('stakeholderEmail')" :label="__('Email')" type="email" />
                <flux:input wire:model="stakeholderPhone" :invalid="$errors->has('stakeholderPhone')" :label="__('Phone')" />
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add stakeholder') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditStakeholderModal" class="md:w-[36rem]">
        <form wire:submit="updateStakeholder" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit stakeholder') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Keep the stakeholder record current so outreach and evidence tracking stay reliable.') }}</flux:text>
            </div>

            <flux:input wire:model="stakeholderName" :invalid="$errors->has('stakeholderName')" :label="__('Name')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="stakeholderType" :invalid="$errors->has('stakeholderType')" :label="__('Type')">
                    @foreach ($stakeholderTypes as $stakeholderTypeOption)
                        <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="stakeholderOrganization" :invalid="$errors->has('stakeholderOrganization')" :label="__('Organization')" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="stakeholderEmail" :invalid="$errors->has('stakeholderEmail')" :label="__('Email')" type="email" />
                <flux:input wire:model="stakeholderPhone" :invalid="$errors->has('stakeholderPhone')" :label="__('Phone')" />
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showAddImplementingAgencyModal" class="md:w-[30rem]">
        <form wire:submit="storeImplementingAgency" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add implementing agency') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Record the ministries, departments, agencies, and regulators accountable for implementation.') }}</flux:text>
            </div>

            <flux:input wire:model="implementingAgencyName" :invalid="$errors->has('implementingAgencyName')" :label="__('Agency name')" />

            <flux:select wire:model="implementingAgencyType" :invalid="$errors->has('implementingAgencyType')" :label="__('Agency type')">
                @foreach ($implementingAgencyTypes as $agencyTypeOption)
                    <flux:select.option :value="$agencyTypeOption->value">{{ \Illuminate\Support\Str::headline($agencyTypeOption->value) }}</flux:select.option>
                @endforeach
            </flux:select>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add agency') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="confirm-stakeholder-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="`${@js(__('Delete this'))} ${deleteConfirmation.noun || @js(__('record'))}?`"></flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span
                        x-show="deleteConfirmation.title"
                        x-text="`${@js(__('This will permanently remove'))} &quot;${deleteConfirmation.title}&quot; ${@js(__('from the review.'))}`"
                    ></span>
                    <span x-show="! deleteConfirmation.title">{{ __('This will permanently remove the selected item from the review.') }}</span>
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
                        x-on:click="$wire.removeStakeholder(deleteConfirmation.id); resetDeleteConfirmation()"
                        x-bind:disabled="! deleteConfirmation.id"
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
