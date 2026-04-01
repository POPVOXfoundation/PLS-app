<div class="space-y-8">
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Share review') }}</flux:heading>
            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Add someone by email. Existing users get access immediately. New people receive an invitation.') }}
            </flux:text>
        </div>

        @if ($canManageCollaborators)
            <form wire:submit="shareReview" class="flex flex-wrap items-end gap-4">
                <div class="relative min-w-[240px] flex-1">
                    <flux:input
                        wire:model.live.debounce.300ms="inviteCollaboratorEmail"
                        :invalid="$errors->has('inviteCollaboratorEmail')"
                        :label="__('Email')"
                        type="email"
                        placeholder="name@example.com"
                    />

                    @if (count($emailMatches) > 0)
                        <div class="absolute z-10 mt-1 w-full rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-900">
                            @foreach ($emailMatches as $match)
                                <button
                                    type="button"
                                    wire:click="selectMatch({{ $match['id'] }})"
                                    class="flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800"
                                >
                                    <div class="min-w-0">
                                        <div class="truncate font-medium text-zinc-900 dark:text-white">{{ $match['name'] }}</div>
                                        <div class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ $match['email'] }}</div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="w-40">
                    <flux:select
                        wire:model="inviteCollaboratorRole"
                        :invalid="$errors->has('inviteCollaboratorRole')"
                        :label="__('Role')"
                    >
                        @foreach ($collaboratorRoleOptions as $roleOption)
                            <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>

                <flux:button type="submit" variant="primary" icon="paper-airplane">
                    {{ __('Share') }}
                </flux:button>
            </form>
        @else
            <flux:callout icon="lock-closed">
                <flux:callout.text>
                    {{ __('Only an owner can manage who has access to this review.') }}
                </flux:callout.text>
            </flux:callout>
        @endif
    </flux:card>

    <flux:card class="space-y-6">
        <flux:heading size="lg">{{ __('Collaborators') }}</flux:heading>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Email') }}</flux:table.column>
                <flux:table.column>{{ __('Role') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>

            <flux:table.rows>
                @foreach ($review->memberships->sortBy(fn ($membership) => sprintf('%d-%s', $membership->role->value === 'owner' ? 0 : 1, $membership->user->name)) as $membership)
                    <flux:table.row :key="'m-' . $membership->id">
                        <flux:table.cell variant="strong">{{ $membership->user->name }}</flux:table.cell>
                        <flux:table.cell>{{ $membership->user->email }}</flux:table.cell>
                        <flux:table.cell>
                            @if ($canManageCollaborators && $membership->role->value !== 'owner')
                                <div class="flex items-center gap-2" wire:key="membership-role-{{ $membership->id }}">
                                    <flux:select wire:model="collaboratorRoles.{{ $membership->id }}" size="sm">
                                        @foreach ($collaboratorRoleOptions as $roleOption)
                                            <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <flux:button variant="ghost" size="sm" wire:click="updateCollaboratorRole({{ $membership->id }})">
                                        {{ __('Save') }}
                                    </flux:button>
                                </div>
                            @else
                                <flux:badge size="sm" color="{{ $membership->role->value === 'owner' ? 'amber' : 'zinc' }}">{{ $membership->role->label() }}</flux:badge>
                            @endif
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($canManageCollaborators && $membership->role->value !== 'owner')
                                <div class="flex justify-end">
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="removeCollaborator({{ $membership->id }})" />
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach

                @foreach ($review->pendingInvitations as $invitation)
                    <flux:table.row :key="'i-' . $invitation->id">
                        <flux:table.cell class="text-zinc-400 dark:text-zinc-500">{{ __('Invitation pending') }}</flux:table.cell>
                        <flux:table.cell>{{ $invitation->email }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:badge size="sm">{{ $invitation->role->label() }}</flux:badge>
                        </flux:table.cell>
                        <flux:table.cell>
                            @if ($canManageCollaborators)
                                <div class="flex justify-end">
                                    <flux:button variant="ghost" size="sm" icon="trash" wire:click="revokeInvitation({{ $invitation->id }})" />
                                </div>
                            @endif
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </flux:card>
</div>
