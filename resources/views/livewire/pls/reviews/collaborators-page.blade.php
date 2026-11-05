<div class="space-y-6" wire:poll.10s>
    <flux:heading size="xl">{{ __('Team') }}</flux:heading>
    <flux:text>{{ __('See who has access, track invitations and follow changes to the shared record.') }}</flux:text>
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Share review') }}</flux:heading>
        @if($canManageCollaborators)
        <flux:text>{{ __('Everyone receives an invitation, including existing users. Access starts when they accept using the invited email address.') }}</flux:text>
        <form wire:submit="shareReview" class="flex flex-wrap items-end gap-3">
            <flux:input wire:model="inviteCollaboratorEmail" type="email" :label="__('Email')" class="min-w-60 flex-1" required />
            <flux:select wire:model="inviteCollaboratorRole" :label="__('Role')" class="min-w-40">@foreach($collaboratorRoleOptions as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</flux:select>
            <flux:button type="submit" variant="primary" wire:loading.attr="disabled">{{ __('Send invitation') }}</flux:button>
        </form>
        @else <flux:text>{{ __('Only an owner can manage who has access to this review.') }}</flux:text> @endif
    </flux:card>
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Team members') }}</flux:heading>
        <flux:text>{{ __('Joined dates are shown for accepted invitations. Older access grants do not prove that a person signed in. Contributions below refer to shared record activity, not private chats.') }}</flux:text>
        <div class="overflow-x-auto"><table class="w-full text-left text-sm"><thead><tr><th class="p-2">{{ __('Person') }}</th><th class="p-2">{{ __('Role') }}</th><th class="p-2">{{ __('Membership') }}</th><th class="p-2">{{ __('Latest recorded contribution') }}</th><th></th></tr></thead><tbody>
        @foreach($review->memberships as $member)
            @php($joined = $review->invitations->first(fn ($invitation) => mb_strtolower($invitation->email) === mb_strtolower($member->user?->email ?? '') && $invitation->accepted_at))
            @php($latest = $activities->firstWhere('user_id', $member->user_id))
            <tr class="border-t align-top" wire:key="member-{{ $member->id }}">
                <td class="p-2"><strong>{{ $member->user?->name }}</strong><br><span class="text-zinc-500">{{ $member->user?->email }}</span></td>
                <td class="p-2">@if($canManageCollaborators && ! $member->isOwner())<flux:select wire:model="collaboratorRoles.{{ $member->id }}" aria-label="Role for {{ $member->user?->name }}">@foreach($collaboratorRoleOptions as $role)<option value="{{ $role->value }}">{{ $role->label() }}</option>@endforeach</flux:select><flux:button size="sm" class="mt-2" wire:click="updateCollaboratorRole({{ $member->id }})">{{ __('Save role') }}</flux:button>@else{{ $member->role->label() }}@endif</td>
                <td class="p-2">{{ $joined ? __('Joined') : __('Access granted') }}<br>{{ ($joined?->accepted_at ?? $member->created_at)?->format('j M Y H:i') }}</td>
                <td class="p-2">{{ $latest ? $latest->action.' '.$latest->title : __('None in recent activity') }} @if($latest)<br><span class="text-xs text-zinc-500">{{ $latest->created_at->format('j M Y H:i') }}</span>@endif</td>
                <td class="p-2">@if($canManageCollaborators && ! $member->isOwner())<flux:button size="sm" variant="danger" wire:click="removeCollaborator({{ $member->id }})" wire:confirm="Remove this person's access?">{{ __('Remove') }}</flux:button>@endif</td>
            </tr>
        @endforeach
        </tbody></table></div>
    </flux:card>
    @if($canManageCollaborators)
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Invitations') }}</flux:heading>
        <flux:text>{{ __('Email status is separate from membership. Sent means accepted by the mail service; inbox delivery is not confirmed. Queued for a long time may mean the worker is stopped. Not configured means the app is using a logging/test mail transport.') }}</flux:text>
        @forelse($review->pendingInvitations as $invite)
        <div class="border-t pt-3 space-y-2" wire:key="delivery-{{ $invite->id }}">
            <p class="text-sm"><strong>{{ $invite->email }}</strong> · {{ $invite->role->label() }} · {{ $invite->expires_at?->isPast() ? __('Expired') : __('Not yet joined') }} · {{ __('Email: :status', ['status' => \Illuminate\Support\Str::headline($invite->mail_status)]) }} @if($invite->mail_sent_at) · {{ $invite->mail_sent_at->format('j M Y H:i') }} @endif</p>
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" wire:click="resendInvitation({{ $invite->id }})">{{ __('Resend invitation') }}</flux:button>
                <flux:button size="sm" x-on:click="navigator.clipboard.writeText(@js(route('pls.invitations.accept', $invite->token))).then(() => $el.textContent = 'Link copied')">{{ __('Copy invitation link') }}</flux:button>
                <flux:button size="sm" variant="danger" wire:click="revokeInvitation({{ $invite->id }})" wire:confirm="Revoke this invitation?">{{ __('Revoke') }}</flux:button>
            </div>
        </div>
        @empty <flux:text>{{ __('No pending invitations.') }}</flux:text> @endforelse
    </flux:card>
    @endif
    <flux:card class="space-y-4">
        <flux:heading size="lg">{{ __('Recent shared record activity') }}</flux:heading>
        <flux:text>{{ __('Private assistant conversations are not shared. This log tracks changes from this release onward and shows the latest 100 events.') }}</flux:text>
        @forelse ($activities as $activity)
            <p class="border-t pt-2 text-sm"><strong>{{ $activity->user?->name ?? __('Background processing') }}</strong> {{ $activity->action }} {{ $activity->subjectLabel() }}: {{ $activity->title }} <span class="text-zinc-500">· {{ $activity->created_at->format('j M Y H:i') }}</span></p>
        @empty <flux:text>{{ __('No shared changes recorded yet.') }}</flux:text> @endforelse
    </flux:card>
</div>
