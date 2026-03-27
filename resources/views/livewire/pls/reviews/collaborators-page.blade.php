<div class="grid gap-8 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.4fr)]">
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Invite a collaborator') }}</flux:heading>
            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Only people you add here can access this review.') }}
            </flux:text>
        </div>

        @if ($canManageCollaborators)
            @if ($availableCollaborators->isEmpty())
                <flux:callout icon="check-circle">
                    <flux:callout.text>
                        {{ __('Everyone who can be added is already on this review.') }}
                    </flux:callout.text>
                </flux:callout>
            @else
                <form wire:submit="inviteCollaborator" class="space-y-4">
                    <flux:select
                        wire:model.live="inviteCollaboratorUserId"
                        :invalid="$errors->has('inviteCollaboratorUserId')"
                        :label="__('User')"
                        :placeholder="__('Select a user')"
                    >
                        @foreach ($availableCollaborators as $availableCollaborator)
                            <flux:select.option :value="$availableCollaborator->id">
                                {{ $availableCollaborator->name }} · {{ $availableCollaborator->email }}
                            </flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select
                        wire:model="inviteCollaboratorRole"
                        :invalid="$errors->has('inviteCollaboratorRole')"
                        :label="__('Role')"
                    >
                        @foreach ($collaboratorRoleOptions as $roleOption)
                            <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="flex justify-end">
                        <flux:button type="submit" variant="primary" icon="user-plus">
                            {{ __('Invite') }}
                        </flux:button>
                    </div>
                </form>
            @endif
        @else
            <flux:callout icon="lock-closed">
                <flux:callout.text>
                    {{ __('Only an owner can manage who has access to this review.') }}
                </flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    <flux:card class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <flux:heading size="lg">{{ __('Collaborators') }}</flux:heading>
        </div>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('User') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column>{{ __('Access source') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($review->memberships->sortBy(fn ($membership) => sprintf('%d-%s', $membership->role->value === 'owner' ? 0 : 1, $membership->user->name)) as $membership)
                    <flux:table.row :key="$membership->id">
                        <flux:table.cell variant="strong">
                            <div class="min-w-0">
                                <div class="truncate">{{ $membership->user->name }}</div>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $membership->user->email }}</flux:text>
                            </div>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($canManageCollaborators && $membership->role->value !== 'owner')
                                <div class="flex items-center gap-2" wire:key="membership-role-{{ $membership->id }}">
                                    <flux:select wire:model="collaboratorRoles.{{ $membership->id }}" size="sm">
                                        @foreach ($collaboratorRoleOptions as $roleOption)
                                            <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        wire:click="updateCollaboratorRole({{ $membership->id }})"
                                    >
                                        {{ __('Save') }}
                                    </flux:button>
                                </div>
                            @else
                                <flux:badge size="sm">{{ $membership->role->label() }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($membership->role->value === 'owner')
                                <div class="space-y-0.5">
                                    <div>{{ __('Created the review') }}</div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Added automatically as owner') }}</flux:text>
                                </div>
                            @elseif ($membership->invitedBy)
                                <div class="space-y-0.5">
                                    <div>{{ __('Invited by :name', ['name' => $membership->invitedBy->name]) }}</div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Explicit access') }}</flux:text>
                                </div>
                            @else
                                <div class="space-y-0.5">
                                    <div>{{ __('Access source not recorded') }}</div>
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('The collaborator still has explicit access.') }}</flux:text>
                                </div>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($canManageCollaborators && $membership->role->value !== 'owner')
                                <div class="flex justify-end">
                                    <flux:button
                                        variant="danger"
                                        size="sm"
                                        icon="user-minus"
                                        wire:click="removeCollaborator({{ $membership->id }})"
                                    >
                                        {{ __('Remove') }}
                                    </flux:button>
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
