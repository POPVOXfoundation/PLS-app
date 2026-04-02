@php
    $activeVersion = $selectedPlaybookRecord?->activeVersion;
@endphp

<div class="flex h-full w-full flex-1 flex-col gap-6">
    <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
        <div class="space-y-1">
            <flux:heading size="xl" level="1">{{ __('Assistant playbooks') }}</flux:heading>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Manage tab-specific assistant behavior with structured, versioned records.') }}
            </flux:text>
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" :href="route('dashboard')" wire:navigate>
                {{ __('Back to dashboard') }}
            </flux:button>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2 border-b border-zinc-200 pb-3 dark:border-zinc-800">
        @foreach ($tabOptions as $tab)
            <button
                type="button"
                wire:click="selectTab(@js($tab['key']))"
                @class([
                    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium',
                    'border-violet-600 bg-violet-600 text-white dark:border-violet-500 dark:bg-violet-500 dark:text-white' => $selectedTabKey === $tab['key'],
                    'border-zinc-200 bg-white text-zinc-600 hover:border-zinc-300 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-400 dark:hover:border-zinc-600 dark:hover:bg-zinc-700' => $selectedTabKey !== $tab['key'],
                ])
            >
                {{ $tab['label'] }}
            </button>
        @endforeach
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <form wire:submit="saveVersion">
            <flux:card class="space-y-6">
                <div class="flex items-start justify-between">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2">
                            <flux:heading size="lg">{{ $tabLabel }}</flux:heading>
                            @if ($activeVersion)
                                <flux:badge size="sm">{{ 'v' . $activeVersion->version_number }}</flux:badge>
                            @else
                                <flux:badge size="sm" color="zinc">{{ __('Config fallback') }}</flux:badge>
                            @endif
                        </div>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $editorSource }}</flux:text>
                    </div>

                    <flux:button type="button" variant="ghost" size="sm" icon="arrow-path" wire:click="resetEditor">
                        {{ __('Reset') }}
                    </flux:button>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <flux:input wire:model="tabLabel" :invalid="$errors->has('tabLabel')" :label="__('Tab label')" />
                    <flux:input wire:model="role" :invalid="$errors->has('role')" :label="__('Role')" />
                    <div class="space-y-1 lg:col-span-2">
                        <flux:textarea wire:model="intro" :invalid="$errors->has('intro')" :label="__('Intro')" rows="2" />
                    </div>
                </div>

                <hr class="border-zinc-200 dark:border-zinc-800" />

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-1">
                        <flux:textarea wire:model="suggestedPromptsText" :invalid="$errors->has('suggestedPromptsText')" :label="__('Suggested prompts')" rows="4" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One per line.') }}</flux:text>
                    </div>
                    <div class="space-y-1">
                        <flux:textarea wire:model="objectivesText" :invalid="$errors->has('objectivesText')" :label="__('Objectives')" rows="4" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One per line.') }}</flux:text>
                    </div>
                    <div class="space-y-1">
                        <flux:textarea wire:model="allowedCapabilitiesText" :invalid="$errors->has('allowedCapabilitiesText')" :label="__('Allowed capabilities')" rows="4" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One per line.') }}</flux:text>
                    </div>
                    <div class="space-y-1">
                        <flux:textarea wire:model="disallowedCapabilitiesText" :invalid="$errors->has('disallowedCapabilitiesText')" :label="__('Disallowed capabilities')" rows="4" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Optional. One per line.') }}</flux:text>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="space-y-1">
                        <flux:textarea wire:model="rulesText" :invalid="$errors->has('rulesText')" :label="__('Rules')" rows="5" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One per line.') }}</flux:text>
                    </div>
                    <div class="space-y-1">
                        <flux:textarea wire:model="guardrailsText" :invalid="$errors->has('guardrailsText')" :label="__('Guardrails')" rows="5" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One per line.') }}</flux:text>
                    </div>
                    <div class="space-y-1">
                        <flux:textarea wire:model="responseStyleText" :invalid="$errors->has('responseStyleText')" :label="__('Response style')" rows="4" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('One per line.') }}</flux:text>
                    </div>
                    <div class="space-y-1">
                        <flux:textarea wire:model="changeNote" :invalid="$errors->has('changeNote')" :label="__('Change note')" rows="4" />
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Summarize what changed.') }}</flux:text>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Saving creates a new active version.') }}
                    </flux:text>
                    <flux:button variant="primary" type="submit" icon="bookmark-square">
                        {{ __('Save new version') }}
                    </flux:button>
                </div>
            </flux:card>
        </form>

        <aside class="xl:sticky xl:top-24 xl:self-start">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="base">{{ __('Version history') }}</flux:heading>
                </div>

                @if ($selectedPlaybookRecord === null || $selectedPlaybookRecord->versions->isEmpty())
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No versions yet. Using config fallback.') }}
                    </flux:text>
                @else
                    <div class="space-y-2">
                        @foreach ($selectedPlaybookRecord->versions as $version)
                            <div wire:key="version-{{ $version->id }}" class="rounded-lg border border-zinc-200 px-3 py-2.5 dark:border-zinc-800">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ 'v' . $version->version_number }}</span>
                                        @if ($selectedPlaybookRecord->active_version_id === $version->id)
                                            <flux:badge size="sm" color="green">{{ __('Active') }}</flux:badge>
                                        @endif
                                    </div>
                                    <div class="flex gap-1">
                                        <flux:button type="button" variant="ghost" size="xs" wire:click="loadVersion({{ $version->id }})">
                                            {{ __('Load') }}
                                        </flux:button>
                                        @if ($selectedPlaybookRecord->active_version_id !== $version->id)
                                            <flux:button type="button" variant="ghost" size="xs" wire:click="activateVersion({{ $version->id }})">
                                                {{ __('Activate') }}
                                            </flux:button>
                                        @endif
                                    </div>
                                </div>
                                @if ($version->change_note)
                                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ $version->change_note }}</flux:text>
                                @endif
                                <flux:text class="mt-0.5 text-[11px] text-zinc-400 dark:text-zinc-500">
                                    {{ $version->created_at?->format('M j, Y') }} · {{ $version->createdBy?->name ?? __('System import') }}
                                </flux:text>
                            </div>
                        @endforeach
                    </div>
                @endif
            </flux:card>
        </aside>
    </div>
</div>
