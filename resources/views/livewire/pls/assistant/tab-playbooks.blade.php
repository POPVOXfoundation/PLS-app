@php
    $activeVersion = $selectedPlaybookRecord?->activeVersion;
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Assistant playbooks') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Manage tab-specific assistant behavior with structured, versioned records. Global system rules stay in code and config.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" :href="route('dashboard')" wire:navigate>
                {{ __('Back to dashboard') }}
            </flux:button>
        </div>
    </div>

    <flux:callout icon="information-circle">
        <flux:callout.text>
            {{ __('This screen only manages tab playbooks. System rules and source documents remain separate layers and are not edited here.') }}
        </flux:callout.text>
    </flux:callout>

    <div class="grid gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
        <flux:card class="space-y-4">
            <div class="space-y-1">
                <flux:heading size="lg">{{ __('Tabs') }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('Select a workspace tab to inspect its active version and draft a new one.') }}
                </flux:text>
            </div>

            <div class="space-y-2">
                @foreach ($tabOptions as $tab)
                    <button
                        type="button"
                        wire:click="selectTab(@js($tab['key']))"
                        @class([
                            'flex w-full items-start justify-between rounded-xl border px-3 py-3 text-left',
                            'border-zinc-900 bg-zinc-50 dark:border-zinc-200 dark:bg-zinc-800/70' => $selectedTabKey === $tab['key'],
                            'border-zinc-200 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:border-zinc-700 dark:hover:bg-zinc-900/70' => $selectedTabKey !== $tab['key'],
                        ])
                    >
                        <div class="min-w-0">
                            <div class="truncate text-sm font-medium text-zinc-950 dark:text-white">{{ $tab['label'] }}</div>
                            <div class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                {{ $tab['active_version_label'] }}
                                @if ($tab['versions_count'] > 0)
                                    · {{ trans_choice('{1} :count version|[2,*] :count versions', $tab['versions_count'], ['count' => $tab['versions_count']]) }}
                                @endif
                            </div>
                        </div>

                        <flux:badge size="sm" :color="$tab['has_db_record'] ? 'green' : 'zinc'">
                            {{ $tab['has_db_record'] ? __('DB') : __('Config') }}
                        </flux:badge>
                    </button>
                @endforeach
            </div>
        </flux:card>

        <div class="space-y-6">
            <flux:card class="space-y-5">
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $tabLabel }}</flux:heading>
                            @if ($activeVersion)
                                <flux:badge size="sm">{{ 'v' . $activeVersion->version_number }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Config fallback') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Editor source') }}: {{ $editorSource }}
                        </flux:text>
                    </div>

                    <flux:button type="button" variant="ghost" wire:click="resetEditor">
                        {{ __('Reset editor') }}
                    </flux:button>
                </div>

                <form wire:submit="saveVersion" class="space-y-6">
                    <div class="grid gap-4 md:grid-cols-2">
                        <flux:input
                            wire:model="tabLabel"
                            :invalid="$errors->has('tabLabel')"
                            :label="__('Tab label')"
                        />

                        <flux:input
                            wire:model="role"
                            :invalid="$errors->has('role')"
                            :label="__('Role')"
                        />
                    </div>

                    <flux:textarea
                        wire:model="intro"
                        :invalid="$errors->has('intro')"
                        :label="__('Intro')"
                        rows="3"
                    />

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="objectivesText"
                                :invalid="$errors->has('objectivesText')"
                                :label="__('Objectives')"
                                rows="6"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One objective per line.') }}</flux:text>
                        </div>

                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="suggestedPromptsText"
                                :invalid="$errors->has('suggestedPromptsText')"
                                :label="__('Suggested prompts')"
                                rows="6"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One prompt per line.') }}</flux:text>
                        </div>

                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="allowedCapabilitiesText"
                                :invalid="$errors->has('allowedCapabilitiesText')"
                                :label="__('Allowed capabilities')"
                                rows="6"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One capability per line.') }}</flux:text>
                        </div>

                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="disallowedCapabilitiesText"
                                :invalid="$errors->has('disallowedCapabilitiesText')"
                                :label="__('Disallowed capabilities')"
                                rows="6"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional. One capability per line.') }}</flux:text>
                        </div>

                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="rulesText"
                                :invalid="$errors->has('rulesText')"
                                :label="__('Rules')"
                                rows="6"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One rule per line.') }}</flux:text>
                        </div>

                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="guardrailsText"
                                :invalid="$errors->has('guardrailsText')"
                                :label="__('Guardrails')"
                                rows="6"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One guardrail per line.') }}</flux:text>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="responseStyleText"
                                :invalid="$errors->has('responseStyleText')"
                                :label="__('Response style')"
                                rows="4"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One response-style instruction per line.') }}</flux:text>
                        </div>

                        <div class="space-y-2">
                            <flux:textarea
                                wire:model="changeNote"
                                :invalid="$errors->has('changeNote')"
                                :label="__('Change note')"
                                rows="4"
                            />
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Summarize what changed in this version.') }}</flux:text>
                        </div>
                    </div>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit" icon="bookmark-square">
                            {{ __('Save new active version') }}
                        </flux:button>
                    </div>
                </form>
            </flux:card>

            <flux:card class="space-y-4">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('Version history') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Inspect previous versions, load one into the editor, or make it active again.') }}
                    </flux:text>
                </div>

                @if ($selectedPlaybookRecord === null || $selectedPlaybookRecord->versions->isEmpty())
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No DB versions exist yet for this tab. The assistant is currently using the config fallback.') }}
                    </flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Version') }}</flux:table.column>
                            <flux:table.column>{{ __('Change note') }}</flux:table.column>
                            <flux:table.column>{{ __('Created') }}</flux:table.column>
                            <flux:table.column>{{ __('By') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>

                        <flux:table.rows>
                            @foreach ($selectedPlaybookRecord->versions as $version)
                                <flux:table.row :key="$version->id">
                                    <flux:table.cell variant="strong">
                                        <div class="flex items-center gap-2">
                                            <span>{{ 'v' . $version->version_number }}</span>
                                            @if ($selectedPlaybookRecord->active_version_id === $version->id)
                                                <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ $version->change_note }}</flux:table.cell>
                                    <flux:table.cell>{{ $version->created_at?->format('M j, Y g:i A') }}</flux:table.cell>
                                    <flux:table.cell>{{ $version->createdBy?->name ?? __('System import') }}</flux:table.cell>
                                    <flux:table.cell>
                                        <div class="flex justify-end gap-2">
                                            <flux:button type="button" variant="ghost" size="sm" wire:click="loadVersion({{ $version->id }})">
                                                {{ __('Load') }}
                                            </flux:button>

                                            @if ($selectedPlaybookRecord->active_version_id !== $version->id)
                                                <flux:button type="button" variant="ghost" size="sm" wire:click="activateVersion({{ $version->id }})">
                                                    {{ __('Activate') }}
                                                </flux:button>
                                            @endif
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>
        </div>
    </div>
</div>
