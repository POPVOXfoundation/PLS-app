<div
    x-data="{
        deleteConfirmation: { type: '', id: null, title: '', noun: '' },
        setDeleteConfirmation(type, id, title, noun) {
            this.deleteConfirmation = { type, id, title, noun };
        },
        resetDeleteConfirmation() {
            this.deleteConfirmation = { type: '', id: null, title: '', noun: '' };
        }
    }"
    class="flex h-full w-full flex-1 flex-col gap-6"
>
    @php
        $reviewAssignmentLabel = $review->assignmentLabel();
        $reviewLocationParts = $review->assignmentLocationParts();
    @endphp

    @if (session('status'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-900/60 dark:bg-green-950/40 dark:text-green-200">
            {{ session('status') }}
        </div>
    @endif

    {{-- ── Header ── --}}
    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
        <div class="space-y-2">
            <div class="flex flex-wrap items-center gap-3">
                <flux:heading size="xl" level="1">{{ $review->title }}</flux:heading>
                <flux:badge>{{ $review->statusLabel() }}</flux:badge>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ $reviewAssignmentLabel }}@if ($reviewLocationParts !== []) · {{ implode(' · ', $reviewLocationParts) }}@endif
            </flux:text>

            @if ($review->description)
                <flux:text class="max-w-4xl">{{ $review->description }}</flux:text>
            @endif
        </div>

        <div class="flex gap-2">
            <flux:button variant="ghost" icon="arrow-left" :href="route('pls.reviews.index')" wire:navigate>
                {{ __('All reviews') }}
            </flux:button>
            <flux:button variant="primary" icon="plus" :href="route('pls.reviews.create')" wire:navigate>
                {{ __('New review') }}
            </flux:button>
        </div>
    </div>

    {{-- ── Progress summary ── --}}
    <flux:card class="space-y-3">
        <div class="flex items-center justify-between">
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Step :current of :total', ['current' => $review->current_step_number, 'total' => $review->steps->count()]) }}
                · {{ $review->currentStepTitle() }}
            </flux:text>
            <flux:text class="text-sm font-medium tabular-nums">{{ $review->progressPercentage() }}%</flux:text>
        </div>
        <flux:progress :value="$review->progressPercentage()" />
    </flux:card>

    @if ($workspaceGuidance)
        <flux:card class="space-y-4">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:badge size="sm">{{ __('Current workspace focus') }}</flux:badge>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Best next area: :tab', ['tab' => $workspaceGuidance['tab']]) }}
                        </flux:text>
                    </div>

                    <div class="space-y-1">
                        <flux:heading size="lg">{{ $workspaceGuidance['title'] }}</flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ $workspaceGuidance['summary'] }}
                        </flux:text>
                    </div>
                </div>

                <div class="rounded-xl border border-zinc-200/80 bg-zinc-50/70 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-300 lg:max-w-sm">
                    <span class="block text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Do next') }}</span>
                    <span class="mt-2 block">{{ $workspaceGuidance['action'] }}</span>
                </div>
            </div>
        </flux:card>
    @endif

    {{-- ── Tabbed content ── --}}
    <flux:tab.group class="[&_[data-flux-tab-panel]]:pt-4">
        <flux:tabs>
            <flux:tab name="workflow" icon="list-bullet">{{ __('Workflow') }}</flux:tab>
            <flux:tab name="collaborators" icon="user-plus">{{ __('Collaborators') }}</flux:tab>
            <flux:tab name="legislation" icon="scale">{{ __('Legislation') }}</flux:tab>
            <flux:tab name="documents" icon="document-text">{{ __('Documents') }}</flux:tab>
            <flux:tab name="stakeholders" icon="users">{{ __('Stakeholders') }}</flux:tab>
            <flux:tab name="consultations" icon="chat-bubble-left-right">{{ __('Consultations') }}</flux:tab>
            <flux:tab name="analysis" icon="light-bulb">{{ __('Analysis') }}</flux:tab>
            <flux:tab name="reports" icon="clipboard-document-list">{{ __('Reports') }}</flux:tab>
        </flux:tabs>

        {{-- ════════════════════════════════════════════════
             TAB: Workflow
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="workflow">
            <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                {{-- Step list --}}
                <flux:card class="space-y-2 !p-4">
                    @foreach ($review->steps as $step)
                        @php
                            $isSelected = $selectedStep && $selectedStep->id === $step->id;
                            $isCurrent = $review->current_step_number === $step->step_number;
                        @endphp

                        <button
                            type="button"
                            wire:click="selectStep({{ $step->step_number }})"
                            class="flex w-full items-center justify-between gap-3 rounded-lg border px-4 py-3 text-left transition-colors {{ $isSelected ? 'border-accent bg-accent/5 ring-1 ring-accent/20 dark:border-accent dark:bg-accent/10' : 'border-zinc-200 hover:bg-zinc-50 dark:border-zinc-800 dark:hover:bg-zinc-800/60' }}"
                        >
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $step->step_number }}.</span>
                                    <span class="truncate text-sm font-medium {{ $isSelected ? 'text-accent-content dark:text-accent-content' : 'text-zinc-900 dark:text-white' }}">{{ $step->title }}</span>
                                </div>
                                <span class="mt-0.5 block text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $step->statusLabel() }}
                                    @if ($step->completed_at)
                                        · {{ $step->completed_at->toFormattedDateString() }}
                                    @elseif ($step->started_at)
                                        · {{ __('Started :date', ['date' => $step->started_at->toFormattedDateString()]) }}
                                    @endif
                                </span>
                            </div>

                            <div class="flex shrink-0 items-center gap-2">
                                @if ($isCurrent)
                                    <flux:badge size="sm" color="violet">{{ __('Current') }}</flux:badge>
                                @endif
                            </div>
                        </button>
                    @endforeach
                </flux:card>

                {{-- Selected step detail --}}
                @if ($selectedStep)
                    <flux:card class="space-y-4">
                        <flux:badge size="sm">{{ __('Step :number', ['number' => $selectedStep->step_number]) }}</flux:badge>

                        <div class="space-y-1">
                            <flux:heading size="lg">{{ $selectedStep->title }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $this->stepContext($selectedStep) }}</flux:text>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-3">
                            @foreach ($this->stepMetricCards($review, $selectedStep) as $metric)
                                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $metric['label'] }}</flux:text>
                                    <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $metric['value'] }}</p>
                                </div>
                            @endforeach
                        </div>

                        <flux:separator variant="subtle" />

                        <flux:table>
                            <flux:table.rows>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">{{ __('Status') }}</flux:table.cell>
                                    <flux:table.cell>{{ $selectedStep->statusLabel() }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">{{ __('Started') }}</flux:table.cell>
                                    <flux:table.cell>{{ $selectedStep->started_at?->toDayDateTimeString() ?? __('Not started') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">{{ __('Completed') }}</flux:table.cell>
                                    <flux:table.cell>{{ $selectedStep->completed_at?->toDayDateTimeString() ?? __('In progress') }}</flux:table.cell>
                                </flux:table.row>
                                <flux:table.row>
                                    <flux:table.cell variant="strong">{{ __('Notes') }}</flux:table.cell>
                                    <flux:table.cell>{{ $selectedStep->notes ?? __('No notes recorded.') }}</flux:table.cell>
                                </flux:table.row>
                            </flux:table.rows>
                        </flux:table>
                    </flux:card>
                @endif
            </div>
        </flux:tab.panel>

        <flux:tab.panel name="collaborators">
            <div class="grid gap-6 xl:grid-cols-[minmax(320px,0.9fr)_minmax(0,1.4fr)]">
                <flux:card class="space-y-4">
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <flux:badge size="sm">{{ __('Review access') }}</flux:badge>
                            <flux:badge size="sm" color="zinc">{{ __('Invitation-based') }}</flux:badge>
                        </div>

                        <flux:heading size="lg">{{ __('Access and collaborators') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Only people listed here can open and work in this review. Legislature, jurisdiction, and review group describe institutional context only.') }}
                        </flux:text>
                    </div>

                    <div class="grid gap-3">
                        <div class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/20">
                            <div class="flex items-start gap-3">
                                <div class="rounded-lg bg-amber-100 p-2 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                    <flux:icon.shield-check class="size-4" />
                                </div>
                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-amber-900 dark:text-amber-100">{{ __('Review group does not control access') }}</p>
                                    <p class="text-sm text-amber-800/90 dark:text-amber-200/90">
                                        {{ __('Adding a review group gives the review institutional context. It does not automatically add people or grant access.') }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="grid gap-3 md:grid-cols-2">
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/70">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm">{{ __('Owner') }}</flux:badge>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Can edit and manage access') }}</span>
                                </div>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Owners can work in the review, invite collaborators, change collaborator roles, and remove access.') }}
                                </p>
                            </div>

                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/70">
                                <div class="flex items-center gap-2">
                                    <flux:badge size="sm" color="zinc">{{ __('Editor') }}</flux:badge>
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('Can edit the workspace') }}</span>
                                </div>
                                <p class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Editors can work across the review workspace but cannot invite people, change roles, or remove access.') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    @if ($canManageCollaborators)
                        <div class="space-y-3 rounded-xl border border-zinc-200/80 bg-white/80 p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div class="space-y-1">
                                <flux:heading size="base">{{ __('Invite a collaborator') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Invite an existing user to this review. Access starts only after you add them here.') }}
                                </flux:text>
                            </div>

                            @if ($availableCollaborators->isEmpty())
                                <flux:callout icon="check-circle">
                                    <flux:callout.text>
                                        {{ __('No additional users are available to invite right now. Everyone who can be added is already on this review.') }}
                                    </flux:callout.text>
                                </flux:callout>
                            @else
                                <form wire:submit="inviteCollaborator" class="space-y-4">
                                    <flux:select
                                        wire:model.live="inviteCollaboratorUserId"
                                        :invalid="$errors->has('inviteCollaboratorUserId')"
                                        :label="__('User')"
                                        :description="__('Choose an existing account. This does not create a new user account.')"
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
                                        :label="__('Access level')"
                                        :description="__('Use Owner only when this person should also manage collaborators. Use Editor for regular review work.')"
                                    >
                                        @foreach ($collaboratorRoleOptions as $roleOption)
                                            <flux:select.option :value="$roleOption->value">{{ $roleOption->label() }}</flux:select.option>
                                        @endforeach
                                    </flux:select>

                                    <div class="flex justify-end">
                                        <flux:button type="submit" variant="primary" icon="user-plus">
                                            {{ __('Invite collaborator') }}
                                        </flux:button>
                                    </div>
                                </form>
                            @endif
                        </div>
                    @else
                        <flux:callout icon="lock-closed">
                            <flux:callout.text>
                                {{ __('You can work in this review, but only an owner can change who has access. Ask an owner if collaborators need to be invited, removed, or promoted.') }}
                            </flux:callout.text>
                        </flux:callout>
                    @endif
                </flux:card>

                <flux:card class="space-y-4">
                    <div class="flex items-center justify-between gap-3">
                        <div class="space-y-1">
                            <flux:heading size="lg">{{ __('Current collaborators') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Everyone listed here can access this review. The creator is the primary owner, and any additional access must be granted explicitly.') }}
                            </flux:text>
                        </div>
                        <flux:badge>{{ $review->memberships->count() }}</flux:badge>
                    </div>

                    @if ($review->memberships->count() === 1)
                        <flux:callout icon="user-circle">
                            <flux:callout.text>
                                {{ __('Only the review owner has access right now. Invite editors when the workspace is ready for collaboration.') }}
                            </flux:callout.text>
                        </flux:callout>
                    @endif

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
                                            <div class="flex flex-wrap items-center gap-2">
                                                <div class="truncate">{{ $membership->user->name }}</div>
                                                @if ($membership->user_id === $review->created_by)
                                                    <flux:badge size="sm">{{ __('Primary owner') }}</flux:badge>
                                                @endif
                                            </div>
                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $membership->user->email }}</flux:text>
                                        </div>
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        @if ($canManageCollaborators && $membership->user_id !== $review->created_by)
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
                                        @if ($membership->user_id === $review->created_by)
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
                                        @if ($canManageCollaborators && $membership->user_id !== $review->created_by)
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
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Legislation
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="legislation">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Legislation') }}</flux:heading>
                    <div class="flex gap-2">
                        <flux:modal.trigger name="attach-legislation">
                            <flux:button variant="ghost" size="sm" icon="link">{{ __('Attach existing') }}</flux:button>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="create-legislation">
                            <flux:button variant="primary" size="sm" icon="plus">{{ __('Create new') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                @if ($review->legislation->isEmpty())
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No legislation linked to this review yet.') }}
                    </flux:text>
                @else
                    <flux:table>
                        <flux:table.columns>
                            <flux:table.column>{{ __('Title') }}</flux:table.column>
                            <flux:table.column>{{ __('Relationship') }}</flux:table.column>
                            <flux:table.column>{{ __('Type') }}</flux:table.column>
                            <flux:table.column>{{ __('Date enacted') }}</flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @foreach ($review->legislation as $legislation)
                                <flux:table.row :key="$legislation->id">
                                    <flux:table.cell variant="strong">
                                        {{ $legislation->title }}
                                        @if ($legislation->short_title)
                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ $legislation->short_title }}</flux:text>
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell>
                                        <flux:badge size="sm">{{ \Illuminate\Support\Str::headline((string) $legislation->pivot->relationship_type) }}</flux:badge>
                                    </flux:table.cell>
                                    <flux:table.cell>{{ \Illuminate\Support\Str::headline($legislation->legislation_type->value ?? '') }}</flux:table.cell>
                                    <flux:table.cell>{{ $legislation->date_enacted?->toFormattedDateString() ?? '—' }}</flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            {{-- Attach legislation modal --}}
            <flux:modal name="attach-legislation" class="md:w-96">
                <form wire:submit="attachLegislation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Attach legislation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Link existing legislation from this jurisdiction.') }}</flux:text>
                    </div>

                    @if ($attachableLegislation->isEmpty())
                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('No additional legislation available for this jurisdiction.') }}
                        </flux:text>
                    @else
                        <flux:select wire:model="attachLegislationId" :invalid="$errors->has('attachLegislationId')" :label="__('Legislation')" :placeholder="__('Select legislation')">
                            @foreach ($attachableLegislation as $attachable)
                                <flux:select.option :value="$attachable->id">{{ $attachable->title }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:select wire:model="attachLegislationRelationshipType" :invalid="$errors->has('attachLegislationRelationshipType')" :label="__('Relationship')">
                            @foreach ($legislationRelationshipTypes as $relationshipType)
                                <flux:select.option :value="$relationshipType->value">{{ \Illuminate\Support\Str::headline($relationshipType->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="flex justify-end">
                            <flux:button variant="primary" type="submit">{{ __('Attach') }}</flux:button>
                        </div>
                    @endif
                </form>
            </flux:modal>

            {{-- Create legislation modal --}}
            <flux:modal name="create-legislation" class="md:w-[32rem]">
                <form wire:submit="createLegislation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Create legislation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Create a new record and attach it to this review.') }}</flux:text>
                    </div>

                    <flux:input wire:model="newLegislationTitle" :invalid="$errors->has('newLegislationTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="newLegislationShortTitle" :invalid="$errors->has('newLegislationShortTitle')" :label="__('Short title')" />
                        <flux:select wire:model="newLegislationType" :invalid="$errors->has('newLegislationType')" :label="__('Type')">
                            @foreach ($legislationTypes as $legislationType)
                                <flux:select.option :value="$legislationType->value">{{ \Illuminate\Support\Str::headline($legislationType->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="newLegislationDateEnacted" :invalid="$errors->has('newLegislationDateEnacted')" :label="__('Date enacted')" type="date" />
                        <flux:select wire:model="newLegislationRelationshipType" :invalid="$errors->has('newLegislationRelationshipType')" :label="__('Relationship')">
                            @foreach ($legislationRelationshipTypes as $relationshipType)
                                <flux:select.option :value="$relationshipType->value">{{ \Illuminate\Support\Str::headline($relationshipType->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:textarea wire:model="newLegislationSummary" :invalid="$errors->has('newLegislationSummary')" :label="__('Summary')" rows="3" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Create') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Documents
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="documents">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                    <flux:modal.trigger name="add-document">
                        <flux:button variant="primary" size="sm" icon="plus">{{ __('Add document') }}</flux:button>
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
                            <flux:table.column align="end">{{ __('Actions') }}</flux:table.column>
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
                                        <div class="flex justify-end gap-2">
                                            <flux:modal.trigger name="edit-document">
                                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingDocument({{ $document->id }})">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <flux:modal.trigger name="confirm-workspace-delete">
                                                <flux:button
                                                    variant="ghost"
                                                    size="sm"
                                                    icon="trash"
                                                    :loading="false"
                                                    x-on:click="setDeleteConfirmation('document', {{ $document->id }}, @js($document->title), @js(__('document')))"
                                                >
                                                    {{ __('Delete') }}
                                                </flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    </flux:table.cell>
                                </flux:table.row>
                            @endforeach
                        </flux:table.rows>
                    </flux:table>
                @endif
            </flux:card>

            {{-- Add document modal --}}
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
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Stakeholders
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="stakeholders">
            @php
                $stakeholdersWithSubmissions = $review->stakeholders
                    ->filter(fn ($stakeholder) => $stakeholder->submissions->isNotEmpty())
                    ->values();
                $stakeholdersAwaitingEvidence = $review->stakeholders
                    ->filter(fn ($stakeholder) => $stakeholder->submissions->isEmpty())
                    ->values();
                $stakeholdersMissingContacts = $review->stakeholders
                    ->filter(function ($stakeholder) {
                        $contactDetails = $stakeholder->contact_details ?? [];

                        return ! filled($contactDetails['organization'] ?? null)
                            && ! filled($contactDetails['email'] ?? null)
                            && ! filled($contactDetails['phone'] ?? null);
                    })
                    ->values();
            @endphp

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.9fr)]">
                <flux:card class="space-y-5">
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm">{{ __('Stakeholder planning') }}</flux:badge>
                                <flux:badge size="sm" color="zinc">{{ __('Review workspace') }}</flux:badge>
                            </div>

                            <flux:heading size="lg">{{ __('Stakeholder directory') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Keep the people and organizations involved in the review current, searchable, and ready for evidence intake.') }}
                            </flux:text>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <flux:select wire:model.live="stakeholderTypeFilter" size="sm" class="min-w-44">
                                <flux:select.option value="all">{{ __('All types') }}</flux:select.option>
                                @foreach ($stakeholderTypes as $stakeholderTypeOption)
                                    <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:modal.trigger name="add-stakeholder">
                                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareStakeholderCreate">{{ __('Add stakeholder') }}</flux:button>
                            </flux:modal.trigger>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Stakeholders mapped') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->stakeholders->count() }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('With submissions') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $stakeholdersWithSubmissions->count() }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Missing contact detail') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $stakeholdersMissingContacts->count() }}</p>
                        </div>
                    </div>

                    @if ($review->stakeholders->isEmpty())
                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <flux:heading size="base">{{ __('No stakeholders recorded yet') }}</flux:heading>
                            <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Start the directory with the ministries, implementing institutions, experts, and civil society actors the review team expects to engage.') }}
                            </flux:text>
                            <div class="mt-4">
                                <flux:modal.trigger name="add-stakeholder">
                                    <flux:button variant="primary" icon="plus" wire:click="prepareStakeholderCreate">{{ __('Add the first stakeholder') }}</flux:button>
                                </flux:modal.trigger>
                            </div>
                        </div>
                    @elseif ($filteredStakeholders->isEmpty())
                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                            <flux:heading size="base">{{ __('No stakeholders match this filter') }}</flux:heading>
                            <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Try a different stakeholder type or clear the filter to return to the full directory.') }}
                            </flux:text>
                            <div class="mt-4">
                                <flux:button variant="ghost" icon="x-mark" wire:click="clearStakeholderFilter">{{ __('Clear filter') }}</flux:button>
                            </div>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach ($filteredStakeholders->sortBy('name') as $stakeholder)
                                @php
                                    $contactDetails = $stakeholder->contact_details ?? [];
                                    $hasContactDetails = filled($contactDetails['organization'] ?? null)
                                        || filled($contactDetails['email'] ?? null)
                                        || filled($contactDetails['phone'] ?? null);
                                    $latestSubmission = $stakeholder->submissions
                                        ->sortByDesc(fn ($submission) => $submission->submitted_at?->timestamp ?? $submission->created_at?->timestamp ?? 0)
                                        ->first();
                                @endphp

                                <div class="rounded-2xl border border-zinc-200 p-5 dark:border-zinc-800">
                                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                                        <div class="min-w-0 space-y-3">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <flux:heading size="base">{{ $stakeholder->name }}</flux:heading>
                                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($stakeholder->stakeholder_type->value) }}</flux:badge>
                                                @if ($stakeholder->submissions->isNotEmpty())
                                                    <flux:badge size="sm" color="emerald">{{ __('Evidence received') }}</flux:badge>
                                                @else
                                                    <flux:badge size="sm" color="zinc">{{ __('Awaiting evidence') }}</flux:badge>
                                                @endif
                                            </div>

                                            @if ($hasContactDetails)
                                                <div class="flex flex-wrap gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                    @if ($contactDetails['organization'] ?? null)
                                                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">{{ $contactDetails['organization'] }}</span>
                                                    @endif
                                                    @if ($contactDetails['email'] ?? null)
                                                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">{{ $contactDetails['email'] }}</span>
                                                    @endif
                                                    @if ($contactDetails['phone'] ?? null)
                                                        <span class="rounded-full bg-zinc-100 px-2.5 py-1 dark:bg-zinc-800">{{ $contactDetails['phone'] }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ __('No contact detail captured yet.') }}
                                                </flux:text>
                                            @endif
                                        </div>

                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                                                {{ trans_choice('{0} 0 submissions|{1} 1 submission|[2,*] :count submissions', $stakeholder->submissions->count(), ['count' => $stakeholder->submissions->count()]) }}
                                            </span>

                                            <flux:modal.trigger name="edit-stakeholder">
                                                <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingStakeholder({{ $stakeholder->id }})">
                                                    {{ __('Edit') }}
                                                </flux:button>
                                            </flux:modal.trigger>

                                            <flux:modal.trigger name="add-submission">
                                                <flux:button variant="primary" size="sm" icon="document-plus" wire:click="prepareSubmissionCreate({{ $stakeholder->id }})">
                                                    {{ __('Add submission') }}
                                                </flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid gap-3 md:grid-cols-[minmax(0,1fr)_220px]">
                                        <div class="rounded-xl bg-zinc-50/80 p-4 dark:bg-zinc-900/70">
                                            <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                                {{ $latestSubmission ? __('Latest submission') : __('Evidence status') }}
                                            </flux:text>

                                            @if ($latestSubmission)
                                                <flux:text class="mt-2 text-sm text-zinc-700 dark:text-zinc-300">{{ $latestSubmission->summary }}</flux:text>
                                                <div class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    <span>{{ $latestSubmission->submitted_at?->toFormattedDateString() ?? __('Undated') }}</span>
                                                    @if ($latestSubmission->document)
                                                        <span>{{ $latestSubmission->document->title }}</span>
                                                    @endif
                                                </div>
                                            @else
                                                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ __('No written evidence has been logged for this stakeholder yet.') }}
                                                </flux:text>
                                            @endif
                                        </div>

                                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                            <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                                {{ __('Review-team cue') }}
                                            </flux:text>
                                            <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ $hasContactDetails
                                                    ? __('Contact detail is on file, so this record is ready for outreach and follow-up.')
                                                    : __('Capture an email, phone number, or organization so the team can follow up consistently.') }}
                                            </flux:text>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </flux:card>

                <div class="space-y-6">
                    <flux:card class="space-y-4">
                        <div class="space-y-1">
                            <flux:heading size="lg">{{ __('Review-team cues') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Use these lists to see where the workspace still needs outreach, evidence, or basic stakeholder detail.') }}
                            </flux:text>
                        </div>

                        @if ($review->stakeholders->isEmpty())
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Stakeholder cues will appear once the directory is started.') }}
                            </flux:text>
                        @else
                            <div class="space-y-4">
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                                    <div class="flex items-center justify-between gap-3">
                                        <flux:heading size="base">{{ __('Awaiting written evidence') }}</flux:heading>
                                        <span class="text-sm font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ $stakeholdersAwaitingEvidence->count() }}</span>
                                    </div>

                                    @if ($stakeholdersAwaitingEvidence->isEmpty())
                                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ __('Every stakeholder currently has at least one linked submission.') }}
                                        </flux:text>
                                    @else
                                        <div class="mt-3 space-y-2">
                                            @foreach ($stakeholdersAwaitingEvidence->take(5) as $stakeholder)
                                                <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 dark:bg-zinc-950/60">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $stakeholder->name }}</p>
                                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ \Illuminate\Support\Str::headline($stakeholder->stakeholder_type->value) }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:modal.trigger name="add-submission">
                                                        <flux:button variant="ghost" size="sm" icon="document-plus" wire:click="prepareSubmissionCreate({{ $stakeholder->id }})">
                                                            {{ __('Log evidence') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                                    <div class="flex items-center justify-between gap-3">
                                        <flux:heading size="base">{{ __('Missing contact detail') }}</flux:heading>
                                        <span class="text-sm font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ $stakeholdersMissingContacts->count() }}</span>
                                    </div>

                                    @if ($stakeholdersMissingContacts->isEmpty())
                                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ __('Each stakeholder has at least one piece of contact or organization detail recorded.') }}
                                        </flux:text>
                                    @else
                                        <div class="mt-3 space-y-2">
                                            @foreach ($stakeholdersMissingContacts->take(5) as $stakeholder)
                                                <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 dark:bg-zinc-950/60">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $stakeholder->name }}</p>
                                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ __('Add email, phone, or organization detail.') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:modal.trigger name="edit-stakeholder">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingStakeholder({{ $stakeholder->id }})">
                                                            {{ __('Update') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </flux:card>

                    <flux:card class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Implementing agencies') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Keep the implementation-review step grounded in the institutions responsible for delivery and oversight.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-implementing-agency">
                                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareImplementingAgencyCreate">{{ __('Add agency') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($review->implementingAgencies->isEmpty())
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No implementing agencies recorded yet.') }}
                            </flux:text>
                        @else
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                @foreach ($review->implementingAgencies as $agency)
                                    <div class="flex items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $agency->name }}</p>
                                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                                {{ \Illuminate\Support\Str::headline($agency->agency_type->value) }}
                                            </flux:text>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                </div>
            </div>

            <flux:modal name="add-stakeholder" class="md:w-[36rem]">
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

            <flux:modal name="edit-stakeholder" class="md:w-[36rem]">
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

            <flux:modal name="add-implementing-agency" class="md:w-[30rem]">
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
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Consultations
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="consultations">
            @php
                $completedConsultations = $review->consultations
                    ->filter(fn ($consultation) => $consultation->held_at !== null)
                    ->sortByDesc('held_at');
                $plannedConsultations = $review->consultations
                    ->filter(fn ($consultation) => $consultation->held_at === null)
                    ->sortBy('title');
                $stakeholdersWithSubmissions = $review->stakeholders
                    ->filter(fn ($stakeholder) => $stakeholder->submissions->isNotEmpty())
                    ->values();
                $stakeholdersAwaitingEvidence = $review->stakeholders
                    ->filter(fn ($stakeholder) => $stakeholder->submissions->isEmpty())
                    ->values();
                $consultationStep = $review->steps->firstWhere('step_key', 'consultations');
                $selectedSubmissionStakeholder = $review->stakeholders->firstWhere('id', (int) $submissionStakeholderId);
            @endphp

            <div class="space-y-6">
                <flux:card class="space-y-5">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm">{{ __('Workflow-linked engagement') }}</flux:badge>
                                @if ($consultationStep)
                                    <flux:badge size="sm" color="{{ $review->current_step_number === $consultationStep->step_number ? 'emerald' : 'zinc' }}">
                                        {{ __('Step :number', ['number' => $consultationStep->step_number]) }}
                                    </flux:badge>
                                @endif
                            </div>

                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Consultation and evidence intake') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Keep planned engagement, completed activity, and written evidence in one workspace so the review team can trace participation back to the workflow.') }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/80 px-4 py-3 text-sm text-zinc-600 dark:border-zinc-800 dark:bg-zinc-900/70 dark:text-zinc-300 lg:max-w-sm">
                            <span class="block text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Current workflow') }}</span>
                            <span class="mt-2 block font-medium text-zinc-900 dark:text-white">
                                {{ __('Step :number · :title', ['number' => $review->current_step_number, 'title' => $review->currentStepTitle()]) }}
                            </span>
                            <span class="mt-2 block">
                                {{ $workspaceGuidance['action'] ?? __('Keep engagement records current and tied to the evidence base.') }}
                            </span>
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Consultations held') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $completedConsultations->count() }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Planned consultations') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $plannedConsultations->count() }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Submissions received') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->submissions->count() }}</p>
                        </div>
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Stakeholders with evidence') }}</flux:text>
                            <p class="mt-1 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $stakeholdersWithSubmissions->count() }}</p>
                        </div>
                    </div>
                </flux:card>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.95fr)]">
                    <flux:card class="space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Consultation activity') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Distinguish planned engagement from completed hearings, roundtables, interviews, and public consultations.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-consultation">
                                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareConsultationCreate">{{ __('Add consultation') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        <div class="space-y-5">
                            @if ($review->consultations->isEmpty())
                                <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                                    <flux:heading size="base">{{ __('No consultation activity recorded yet') }}</flux:heading>
                                    <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ __('Use this area for both planned outreach and completed hearings, interviews, roundtables, or public consultations.') }}
                                    </flux:text>
                                    <div class="mt-4">
                                        <flux:modal.trigger name="add-consultation">
                                            <flux:button variant="primary" icon="plus" wire:click="prepareConsultationCreate">{{ __('Add the first consultation') }}</flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </div>
                            @endif

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                        {{ __('Completed') }}
                                    </flux:text>
                                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $completedConsultations->count() }}</span>
                                </div>

                                @if ($completedConsultations->isEmpty())
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No completed consultation activity recorded yet.') }}
                                    </flux:text>
                                @else
                                    <div class="space-y-3">
                                        @foreach ($completedConsultations as $consultation)
                                            <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 space-y-2">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $consultation->title }}</p>
                                                            <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($consultation->consultation_type->value) }}</flux:badge>
                                                            <flux:badge size="sm" color="emerald">{{ __('Completed') }}</flux:badge>
                                                        </div>
                                                        <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                            <span>{{ $consultation->held_at?->toFormattedDateString() }}</span>
                                                            @if ($consultation->document)
                                                                <span>{{ $consultation->document->title }}</span>
                                                            @endif
                                                        </div>
                                                    </div>

                                                    <flux:modal.trigger name="edit-consultation">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingConsultation({{ $consultation->id }})">
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>

                                                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $consultation->summary }}</flux:text>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>

                            <div class="space-y-3 border-t border-zinc-100 pt-5 dark:border-zinc-800/60">
                                <div class="flex items-center justify-between">
                                    <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                        {{ __('Planned') }}
                                    </flux:text>
                                    <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $plannedConsultations->count() }}</span>
                                </div>

                                @if ($plannedConsultations->isEmpty())
                                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No planned consultation work queued yet.') }}
                                    </flux:text>
                                @else
                                    <div class="space-y-3">
                                        @foreach ($plannedConsultations as $consultation)
                                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                                                <div class="flex items-start justify-between gap-3">
                                                    <div class="min-w-0 space-y-2">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $consultation->title }}</p>
                                                            <flux:badge size="sm" color="zinc">{{ \Illuminate\Support\Str::headline($consultation->consultation_type->value) }}</flux:badge>
                                                            <flux:badge size="sm" color="amber">{{ __('Planned') }}</flux:badge>
                                                        </div>
                                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                                            {{ __('Planned engagement activity') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:modal.trigger name="edit-consultation">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingConsultation({{ $consultation->id }})">
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>

                                                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $consultation->summary }}</flux:text>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>
                    </flux:card>

                    <flux:card class="space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Submissions and evidence') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Log written evidence, connect it to stakeholder records, and keep the supporting document trail visible to the review team.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-submission">
                                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareSubmissionCreate" :disabled="$review->stakeholders->isEmpty()">{{ __('Add submission') }}</flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($review->stakeholders->isEmpty())
                            <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <flux:heading size="base">{{ __('Add stakeholders before logging submissions') }}</flux:heading>
                                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Written evidence is attached to stakeholder records. Build the stakeholder directory first, then log submissions from this panel or directly from a stakeholder card.') }}
                                </flux:text>
                            </div>
                        @else
                            <div class="space-y-5">
                                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/70">
                                    <div class="flex items-center justify-between gap-3">
                                        <flux:heading size="base">{{ __('Awaiting written evidence') }}</flux:heading>
                                        <span class="text-sm font-medium tabular-nums text-zinc-500 dark:text-zinc-400">{{ $stakeholdersAwaitingEvidence->count() }}</span>
                                    </div>

                                    @if ($stakeholdersAwaitingEvidence->isEmpty())
                                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                            {{ __('Every stakeholder already has at least one linked submission.') }}
                                        </flux:text>
                                    @else
                                        <div class="mt-3 space-y-2">
                                            @foreach ($stakeholdersAwaitingEvidence->take(5) as $stakeholder)
                                                <div class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 dark:bg-zinc-950/60">
                                                    <div class="min-w-0">
                                                        <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $stakeholder->name }}</p>
                                                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                            {{ __('No submission linked yet.') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:modal.trigger name="add-submission">
                                                        <flux:button variant="ghost" size="sm" icon="document-plus" wire:click="prepareSubmissionCreate({{ $stakeholder->id }})">
                                                            {{ __('Add') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>

                                <div class="space-y-3">
                                    <div class="flex items-center justify-between">
                                        <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                            {{ __('Received submissions') }}
                                        </flux:text>
                                        <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $review->submissions->count() }}</span>
                                    </div>

                                    @if ($review->submissions->isEmpty())
                                        <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                                            <flux:heading size="base">{{ __('No submissions logged yet') }}</flux:heading>
                                            <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                                {{ __('Once written evidence starts arriving, log it here and keep it tied to the stakeholder record that supplied it.') }}
                                            </flux:text>
                                            <div class="mt-4">
                                                <flux:modal.trigger name="add-submission">
                                                    <flux:button variant="primary" icon="plus" wire:click="prepareSubmissionCreate">{{ __('Add the first submission') }}</flux:button>
                                                </flux:modal.trigger>
                                            </div>
                                        </div>
                                    @else
                                        <div class="space-y-3">
                                            @foreach ($review->submissions->sortByDesc(fn ($submission) => $submission->submitted_at?->timestamp ?? $submission->created_at?->timestamp ?? 0) as $submission)
                                                <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0 space-y-2">
                                                            <div class="flex flex-wrap items-center gap-2">
                                                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $submission->stakeholder?->name ?? __('Unknown stakeholder') }}</p>
                                                                @if ($submission->stakeholder)
                                                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($submission->stakeholder->stakeholder_type->value) }}</flux:badge>
                                                                @endif
                                                            </div>
                                                            <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                                <span>{{ $submission->submitted_at?->toFormattedDateString() ?? __('Undated') }}</span>
                                                                @if ($submission->document)
                                                                    <span>{{ $submission->document->title }}</span>
                                                                @endif
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">{{ $submission->summary }}</flux:text>
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </flux:card>
                </div>
            </div>

            <flux:modal name="add-consultation" class="md:w-[34rem]">
                <form wire:submit="storeConsultation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add consultation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Record a planned or completed consultation activity for this review. Leave the date blank to keep it in the planned queue.') }}</flux:text>
                    </div>

                    <flux:input wire:model="consultationTitle" :invalid="$errors->has('consultationTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="consultationType" :invalid="$errors->has('consultationType')" :label="__('Type')">
                            @foreach ($consultationTypes as $consultationTypeOption)
                                <flux:select.option :value="$consultationTypeOption->value">{{ \Illuminate\Support\Str::headline($consultationTypeOption->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="consultationHeldAt" :invalid="$errors->has('consultationHeldAt')" :label="__('Date held')" type="date" />
                    </div>

                    <flux:select wire:model="consultationDocumentId" :invalid="$errors->has('consultationDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                        @foreach ($review->documents as $documentOption)
                            <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="consultationSummary" :invalid="$errors->has('consultationSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add consultation') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-consultation" class="md:w-[34rem]">
                <form wire:submit="updateConsultation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit consultation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Update the schedule, outcome summary, or linked materials for this consultation. Clearing the date returns it to planned activity.') }}</flux:text>
                    </div>

                    <flux:input wire:model="consultationTitle" :invalid="$errors->has('consultationTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="consultationType" :invalid="$errors->has('consultationType')" :label="__('Type')">
                            @foreach ($consultationTypes as $consultationTypeOption)
                                <flux:select.option :value="$consultationTypeOption->value">{{ \Illuminate\Support\Str::headline($consultationTypeOption->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <flux:input wire:model="consultationHeldAt" :invalid="$errors->has('consultationHeldAt')" :label="__('Date held')" type="date" />
                    </div>

                    <flux:select wire:model="consultationDocumentId" :invalid="$errors->has('consultationDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                        @foreach ($review->documents as $documentOption)
                            <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="consultationSummary" :invalid="$errors->has('consultationSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-submission" class="md:w-[34rem]">
                <form wire:submit="storeSubmission" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add submission') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Log written evidence and connect it to the stakeholder record that submitted it.') }}</flux:text>
                    </div>

                    @if ($selectedSubmissionStakeholder)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                                {{ __('Selected stakeholder') }}
                            </flux:text>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $selectedSubmissionStakeholder->name }}</span>
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($selectedSubmissionStakeholder->stakeholder_type->value) }}</flux:badge>
                            </div>
                        </div>
                    @endif

                    <flux:select wire:model="submissionStakeholderId" :invalid="$errors->has('submissionStakeholderId')" :label="__('Stakeholder')" :placeholder="__('Select stakeholder')">
                        @foreach ($review->stakeholders as $stakeholderOption)
                            <flux:select.option :value="$stakeholderOption->id">{{ $stakeholderOption->name }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model="submissionSubmittedAt" :invalid="$errors->has('submissionSubmittedAt')" :label="__('Submitted at')" type="date" />
                        <flux:select wire:model="submissionDocumentId" :invalid="$errors->has('submissionDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($review->documents as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <flux:textarea wire:model="submissionSummary" :invalid="$errors->has('submissionSummary')" :label="__('Summary')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add submission') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Analysis (Findings + Recommendations)
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="analysis">
            <flux:card class="space-y-4">
                <div class="flex items-center justify-between">
                    <flux:heading size="lg">{{ __('Findings & recommendations') }}</flux:heading>
                    <div class="flex gap-2">
                        <flux:modal.trigger name="add-finding">
                            <flux:button variant="ghost" size="sm" icon="plus">{{ __('Add finding') }}</flux:button>
                        </flux:modal.trigger>
                        <flux:modal.trigger name="add-recommendation">
                            <flux:button variant="primary" size="sm" icon="plus">{{ __('Add recommendation') }}</flux:button>
                        </flux:modal.trigger>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Findings') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->findings->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Recommendations') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->recommendations->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Gov. responses') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->governmentResponses->count() }}</p>
                    </div>
                </div>

                @if ($review->findings->isEmpty())
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No findings or recommendations recorded yet.') }}
                    </flux:text>
                @else
                    <div class="space-y-3">
                        @foreach ($review->findings as $finding)
                            <flux:card class="space-y-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 flex-wrap items-center gap-2">
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $finding->title }}</span>
                                        <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($finding->finding_type->value) }}</flux:badge>
                                    </div>

                                    <div class="flex shrink-0 gap-2">
                                        <flux:modal.trigger name="edit-finding">
                                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingFinding({{ $finding->id }})">
                                                {{ __('Edit') }}
                                            </flux:button>
                                        </flux:modal.trigger>

                                        <flux:modal.trigger name="confirm-workspace-delete">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                :loading="false"
                                                x-on:click="setDeleteConfirmation('finding', {{ $finding->id }}, @js($finding->title), @js(__('finding')))"
                                            >
                                                {{ __('Delete') }}
                                            </flux:button>
                                        </flux:modal.trigger>
                                    </div>
                                </div>

                                @if ($finding->summary)
                                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $finding->summary }}</flux:text>
                                @endif

                                @php $recommendations = $review->recommendations->where('finding_id', $finding->id); @endphp

                                @if ($recommendations->isNotEmpty())
                                    <div class="divide-y divide-zinc-100 border-t border-zinc-100 pt-2 dark:divide-zinc-800/60 dark:border-zinc-800/60">
                                        @foreach ($recommendations as $recommendation)
                                            <div class="flex items-start justify-between gap-3 py-2 first:pt-0 last:pb-0">
                                                <div class="min-w-0">
                                                    <span class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $recommendation->title }}</span>
                                                    @if ($recommendation->description)
                                                        <flux:text class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $recommendation->description }}</flux:text>
                                                    @endif
                                                </div>
                                                <div class="flex shrink-0 items-center gap-2">
                                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($recommendation->recommendation_type->value) }}</flux:badge>
                                                    <flux:modal.trigger name="edit-recommendation">
                                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingRecommendation({{ $recommendation->id }})">
                                                            {{ __('Edit') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                    <flux:modal.trigger name="confirm-workspace-delete">
                                                        <flux:button
                                                            variant="ghost"
                                                            size="sm"
                                                            icon="trash"
                                                            :loading="false"
                                                            x-on:click="setDeleteConfirmation('recommendation', {{ $recommendation->id }}, @js($recommendation->title), @js(__('recommendation')))"
                                                        >
                                                            {{ __('Delete') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </flux:card>
                        @endforeach
                    </div>
                @endif
            </flux:card>

            {{-- Add finding modal --}}
            <flux:modal name="add-finding" class="md:w-[32rem]">
                <form wire:submit="storeFinding" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add finding') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Capture a core conclusion from the review evidence.') }}</flux:text>
                    </div>

                    <flux:input wire:model="findingTitle" :invalid="$errors->has('findingTitle')" :label="__('Title')" />

                    <flux:select wire:model="findingType" :invalid="$errors->has('findingType')" :label="__('Type')">
                        @foreach ($findingTypes as $type)
                            <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="findingSummary" :invalid="$errors->has('findingSummary')" :label="__('Summary')" rows="3" />
                    <flux:textarea wire:model="findingDetail" :invalid="$errors->has('findingDetail')" :label="__('Detail')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add finding') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-finding" class="md:w-[32rem]">
                <form wire:submit="updateFinding" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit finding') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Refine the wording, classification, or detail for this finding.') }}</flux:text>
                    </div>

                    <flux:input wire:model="findingTitle" :invalid="$errors->has('findingTitle')" :label="__('Title')" />

                    <flux:select wire:model="findingType" :invalid="$errors->has('findingType')" :label="__('Type')">
                        @foreach ($findingTypes as $type)
                            <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="findingSummary" :invalid="$errors->has('findingSummary')" :label="__('Summary')" rows="3" />
                    <flux:textarea wire:model="findingDetail" :invalid="$errors->has('findingDetail')" :label="__('Detail')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            {{-- Add recommendation modal --}}
            <flux:modal name="add-recommendation" class="md:w-[32rem]">
                <form wire:submit="storeRecommendation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add recommendation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Tie each recommendation to an existing finding.') }}</flux:text>
                    </div>

                    <flux:select wire:model="recommendationFindingId" :invalid="$errors->has('recommendationFindingId')" :label="__('Finding')" :placeholder="__('Select finding')">
                        @foreach ($review->findings as $findingOption)
                            <flux:select.option :value="$findingOption->id">{{ $findingOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="recommendationTitle" :invalid="$errors->has('recommendationTitle')" :label="__('Title')" />

                    <flux:select wire:model="recommendationType" :invalid="$errors->has('recommendationType')" :label="__('Type')">
                        @foreach ($recommendationTypes as $type)
                            <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="recommendationDescription" :invalid="$errors->has('recommendationDescription')" :label="__('Description')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-recommendation" class="md:w-[32rem]">
                <form wire:submit="updateRecommendation" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit recommendation') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Update the recommendation text or move it to a different finding if needed.') }}</flux:text>
                    </div>

                    <flux:select wire:model="recommendationFindingId" :invalid="$errors->has('recommendationFindingId')" :label="__('Finding')" :placeholder="__('Select finding')">
                        @foreach ($review->findings as $findingOption)
                            <flux:select.option :value="$findingOption->id">{{ $findingOption->title }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:input wire:model="recommendationTitle" :invalid="$errors->has('recommendationTitle')" :label="__('Title')" />

                    <flux:select wire:model="recommendationType" :invalid="$errors->has('recommendationType')" :label="__('Type')">
                        @foreach ($recommendationTypes as $type)
                            <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:textarea wire:model="recommendationDescription" :invalid="$errors->has('recommendationDescription')" :label="__('Description')" rows="4" />

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>
        </flux:tab.panel>

        {{-- ════════════════════════════════════════════════
             TAB: Reports
        ════════════════════════════════════════════════ --}}
        <flux:tab.panel name="reports">
            @php
                $reportWorkflowFocus = $this->reportWorkflowFocus($review);
                $publishedFinalReports = $review->reports->filter(
                    fn ($report) => $report->report_type === \App\Domain\Reporting\Enums\ReportType::FinalReport
                        && $report->status === \App\Domain\Reporting\Enums\ReportStatus::Published,
                );
                $awaitingResponseReports = $publishedFinalReports->filter(
                    fn ($report) => $report->governmentResponses->isEmpty(),
                );
                $draftRecommendations = $review->recommendations->take(3);
                $publishedReportCount = $review->reports
                    ->where('status', \App\Domain\Reporting\Enums\ReportStatus::Published)
                    ->count();
                $receivedResponseCount = $review->governmentResponses
                    ->where('response_status', \App\Domain\Reporting\Enums\GovernmentResponseStatus::Received)
                    ->count();
                $reportDocumentTypes = [
                    \App\Domain\Documents\Enums\DocumentType::DraftReport,
                    \App\Domain\Documents\Enums\DocumentType::FinalReport,
                    \App\Domain\Documents\Enums\DocumentType::GroupReport,
                    \App\Domain\Documents\Enums\DocumentType::PolicyReport,
                ];
                $preferredReportDocuments = $review->documents->filter(
                    fn ($document) => in_array($document->document_type, $reportDocumentTypes, true),
                );
                $otherReportDocuments = $review->documents->reject(
                    fn ($document) => in_array($document->document_type, $reportDocumentTypes, true),
                );
                $preferredResponseDocuments = $review->documents->filter(
                    fn ($document) => $document->document_type === \App\Domain\Documents\Enums\DocumentType::GovernmentResponse,
                );
                $otherResponseDocuments = $review->documents->reject(
                    fn ($document) => $document->document_type === \App\Domain\Documents\Enums\DocumentType::GovernmentResponse,
                );
                $selectedReportDocument = $reportDocumentId === ''
                    ? null
                    : $review->documents->firstWhere('id', (int) $reportDocumentId);
                $selectedGovernmentResponseReport = $governmentResponseReportId === ''
                    ? null
                    : $review->reports->firstWhere('id', (int) $governmentResponseReportId);
                $selectedGovernmentResponseLatest = $selectedGovernmentResponseReport
                    ? $this->latestGovernmentResponseForReport($selectedGovernmentResponseReport)
                    : null;
                $selectedGovernmentResponseDocument = $governmentResponseDocumentId === ''
                    ? null
                    : $review->documents->firstWhere('id', (int) $governmentResponseDocumentId);
            @endphp

            <div class="space-y-6">
                @if ($reportWorkflowFocus)
                    <flux:card class="space-y-3">
                        <flux:text class="text-xs font-medium uppercase tracking-[0.18em] text-zinc-400 dark:text-zinc-500">
                            {{ $reportWorkflowFocus['label'] }}
                        </flux:text>

                        <div class="space-y-1">
                            <flux:heading size="lg">{{ $reportWorkflowFocus['title'] }}</flux:heading>
                            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ $reportWorkflowFocus['summary'] }}
                            </flux:text>
                        </div>
                    </flux:card>
                @endif

                <flux:card class="space-y-5">
                    <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                        <div class="space-y-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:badge size="sm">{{ __('End-stage review workspace') }}</flux:badge>
                                <flux:badge size="sm" color="zinc">{{ __('Reports + executive follow-up') }}</flux:badge>
                            </div>

                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Reporting workspace') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Move from findings and recommendations into report drafting, publication tracking, and government follow-up without leaving the review workspace.') }}
                                </flux:text>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2">
                            <flux:modal.trigger name="add-report">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="document-text"
                                    wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::DraftReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                                >
                                    {{ __('New draft report') }}
                                </flux:button>
                            </flux:modal.trigger>

                            <flux:modal.trigger name="add-report">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="clipboard-document-list"
                                    wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::FinalReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                                >
                                    {{ __('New final report') }}
                                </flux:button>
                            </flux:modal.trigger>

                            @if ($review->reports->isNotEmpty())
                                <flux:modal.trigger name="add-government-response">
                                    <flux:button variant="primary" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate">
                                        {{ __('Track response') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endif
                        </div>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Analysis ready') }}</flux:text>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->findings->count() }}/{{ $review->recommendations->count() }}</p>
                            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __(':findings findings and :recommendations recommendations available as drafting inputs.', [
                                    'findings' => $review->findings->count(),
                                    'recommendations' => $review->recommendations->count(),
                                ]) }}
                            </flux:text>
                        </div>
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Report outputs') }}</flux:text>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->reports->count() }}</p>
                            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __(':draft drafts, :final final reports, and :published published outputs are on record.', [
                                    'draft' => $review->reports->where('report_type', \App\Domain\Reporting\Enums\ReportType::DraftReport)->count(),
                                    'final' => $review->reports->where('report_type', \App\Domain\Reporting\Enums\ReportType::FinalReport)->count(),
                                    'published' => $publishedReportCount,
                                ]) }}
                            </flux:text>
                        </div>
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Awaiting follow-up') }}</flux:text>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $awaitingResponseReports->count() }}</p>
                            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Published final reports that still need a request, reply, or overdue marker.') }}
                            </flux:text>
                        </div>
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Responses captured') }}</flux:text>
                            <p class="mt-2 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->governmentResponses->count() }}</p>
                            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __(':received received and :overdue overdue follow-up records are attached to reports.', [
                                    'received' => $receivedResponseCount,
                                    'overdue' => $this->overdueGovernmentResponseCount($review),
                                ]) }}
                            </flux:text>
                        </div>
                    </div>

                    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.95fr)]">
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <div class="space-y-2">
                                <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Drafting inputs from analysis') }}</flux:text>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Reporting stays grounded in the analysis workspace. Findings and recommendations remain the source material for report drafting and response follow-up.') }}
                                </flux:text>
                            </div>

                            @if ($draftRecommendations->isEmpty())
                                <div class="mt-4 rounded-xl border border-dashed border-zinc-300/80 bg-white/80 p-4 dark:border-zinc-700 dark:bg-zinc-950/40">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No recommendations drafted yet') }}</p>
                                    <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('Capture the strongest findings and recommendations in the analysis tab first, then come back here to turn them into report outputs.') }}
                                    </flux:text>
                                </div>
                            @else
                                <div class="mt-4 space-y-3">
                                    @foreach ($draftRecommendations as $recommendation)
                                        <div class="rounded-xl border border-zinc-200 bg-white px-4 py-3 dark:border-zinc-800 dark:bg-zinc-950/50">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $recommendation->title }}</span>
                                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($recommendation->recommendation_type->value) }}</flux:badge>
                                            </div>
                                            @if ($recommendation->finding)
                                                <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                                    {{ __('From finding: :finding', ['finding' => $recommendation->finding->title]) }}
                                                </flux:text>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-800 dark:bg-zinc-950/40">
                            <div class="space-y-2">
                                <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Lifecycle view') }}</flux:text>
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Use this sequence to keep report drafting and government follow-up attached to the same end-stage review record.') }}
                                </flux:text>
                            </div>

                            <div class="mt-4 space-y-3">
                                <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('1. Analysis ready') }}</p>
                                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Sufficient findings and recommendations are in place to support drafting.') }}
                                        </flux:text>
                                    </div>
                                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ $review->recommendations->isNotEmpty() ? __('Ready') : __('Pending') }}
                                    </span>
                                </div>

                                <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('2. Draft and final outputs') }}</p>
                                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Create draft, final, briefing, or summary outputs and keep the linked publication document current.') }}
                                        </flux:text>
                                    </div>
                                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ trans_choice(':count report|:count reports', $review->reports->count(), ['count' => $review->reports->count()]) }}
                                    </span>
                                </div>

                                <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('3. Publication status') }}</p>
                                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Published status and publication dates should be explicit so response obligations are visible.') }}
                                        </flux:text>
                                    </div>
                                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ trans_choice(':count published|:count published', $publishedReportCount, ['count' => $publishedReportCount]) }}
                                    </span>
                                </div>

                                <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                                    <div>
                                        <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('4. Government follow-up') }}</p>
                                        <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Requests, received replies, and overdue follow-up stay attached to the final report they address.') }}
                                        </flux:text>
                                    </div>
                                    <span class="rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1 text-[11px] font-medium text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300">
                                        {{ $awaitingResponseReports->isNotEmpty() ? __('Action needed') : __('Tracked') }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </flux:card>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Reports') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->reports->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Final reports') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->reports->where('report_type', \App\Domain\Reporting\Enums\ReportType::FinalReport)->count() }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Awaiting response') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $this->awaitingGovernmentResponseCount($review) }}</p>
                    </div>
                    <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Overdue responses') }}</flux:text>
                        <p class="mt-1 text-xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $this->overdueGovernmentResponseCount($review) }}</p>
                    </div>
                </div>

                <div class="grid gap-6 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.95fr)]">
                    <flux:card class="space-y-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Reports') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Track draft outputs, final publications, and whether government follow-up has been captured against each report.') }}
                                </flux:text>
                            </div>

                            <flux:modal.trigger name="add-report">
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    icon="plus"
                                    wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::DraftReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                                >
                                    {{ __('Add report') }}
                                </flux:button>
                            </flux:modal.trigger>
                        </div>

                        @if ($review->reports->isEmpty())
                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No report records created yet.') }}
                                </flux:text>
                                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Start by creating a draft or final report record, then link the publication document when it is ready.') }}
                                </flux:text>
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($review->reports->sortByDesc(fn ($report) => $report->published_at?->timestamp ?? $report->created_at?->timestamp ?? 0) as $report)
                                    @php
                                        $responseIndicator = $this->reportResponseIndicator($report);
                                        $latestResponse = $this->latestGovernmentResponseForReport($report);
                                        $statusClasses = match ($report->status) {
                                            \App\Domain\Reporting\Enums\ReportStatus::Published => 'border-emerald-200/80 bg-emerald-50/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300',
                                            \App\Domain\Reporting\Enums\ReportStatus::Archived => 'border-zinc-200 bg-zinc-50 text-zinc-600 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-300',
                                            \App\Domain\Reporting\Enums\ReportStatus::Draft => 'border-amber-200/80 bg-amber-50/80 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/20 dark:text-amber-300',
                                        };
                                    @endphp

                                    <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0 space-y-2">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $report->title }}</p>
                                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($report->report_type->value) }}</flux:badge>
                                                    <span class="inline-flex items-center justify-center rounded-md border px-2.5 py-1 text-[11px] font-medium leading-none {{ $statusClasses }}">
                                                        {{ \Illuminate\Support\Str::headline($report->status->value) }}
                                                    </span>
                                                </div>

                                                <div class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    <span>{{ $report->published_at?->toFormattedDateString() ?? __('Not published') }}</span>
                                                    <span>{{ $report->document?->title ?? __('No linked document') }}</span>
                                                </div>
                                            </div>

                                            <div class="flex shrink-0 flex-wrap items-center justify-end gap-2">
                                                <span class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md border px-2.5 py-1 text-center text-[11px] font-medium leading-none {{ $responseIndicator['classes'] }}">
                                                    {{ $responseIndicator['label'] }}
                                                </span>

                                                @if ($report->report_type === \App\Domain\Reporting\Enums\ReportType::FinalReport && $report->status === \App\Domain\Reporting\Enums\ReportStatus::Published)
                                                    <flux:modal.trigger name="add-government-response">
                                                        <flux:button variant="ghost" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate({{ $report->id }})">
                                                            {{ $latestResponse ? __('Add follow-up') : __('Track response') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                @endif

                                                <flux:modal.trigger name="edit-report">
                                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingReport({{ $report->id }})">
                                                        {{ __('Edit') }}
                                                    </flux:button>
                                                </flux:modal.trigger>

                                                <flux:modal.trigger name="confirm-workspace-delete">
                                                    <flux:button
                                                        variant="ghost"
                                                        size="sm"
                                                        icon="trash"
                                                        :loading="false"
                                                        x-on:click="setDeleteConfirmation('report', {{ $report->id }}, @js($report->title), @js(__('report')))"
                                                    >
                                                        {{ __('Delete') }}
                                                    </flux:button>
                                                </flux:modal.trigger>
                                            </div>
                                        </div>

                                        <div class="mt-4 grid gap-3 sm:grid-cols-3">
                                            <div class="rounded-lg border border-zinc-100 bg-zinc-50/70 px-3 py-2 dark:border-zinc-800/60 dark:bg-zinc-900/60">
                                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Publication') }}</flux:text>
                                                <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $report->published_at?->toFormattedDateString() ?? __('Not published') }}</p>
                                            </div>
                                            <div class="rounded-lg border border-zinc-100 bg-zinc-50/70 px-3 py-2 dark:border-zinc-800/60 dark:bg-zinc-900/60">
                                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Linked document') }}</flux:text>
                                                <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $report->document?->title ?? __('No document linked') }}</p>
                                            </div>
                                            <div class="rounded-lg border border-zinc-100 bg-zinc-50/70 px-3 py-2 dark:border-zinc-800/60 dark:bg-zinc-900/60">
                                                <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Response tracking') }}</flux:text>
                                                <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $responseIndicator['label'] }}</p>
                                            </div>
                                        </div>

                                        @if ($latestResponse)
                                            <div class="mt-4 grid gap-2 border-t border-zinc-100 pt-3 text-sm text-zinc-600 dark:border-zinc-800/60 dark:text-zinc-400 sm:grid-cols-3">
                                                <div>
                                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Latest response') }}</flux:text>
                                                    <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ \Illuminate\Support\Str::headline($latestResponse->response_status->value) }}</p>
                                                </div>
                                                <div>
                                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Received') }}</flux:text>
                                                    <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $latestResponse->received_at?->toFormattedDateString() ?? __('Not recorded') }}</p>
                                                </div>
                                                <div>
                                                    <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Linked document') }}</flux:text>
                                                    <p class="mt-1 truncate text-sm text-zinc-800 dark:text-zinc-200">{{ $latestResponse->document?->title ?? __('No document linked') }}</p>
                                                </div>
                                            </div>

                                            @if ($latestResponse->summary)
                                                <flux:text class="mt-3 text-sm text-zinc-600 dark:text-zinc-400">
                                                    {{ $latestResponse->summary }}
                                                </flux:text>
                                            @endif
                                        @elseif ($report->report_type === \App\Domain\Reporting\Enums\ReportType::FinalReport && $report->status === \App\Domain\Reporting\Enums\ReportStatus::Published)
                                            <div class="mt-4 rounded-xl border border-dashed border-amber-200/80 bg-amber-50/70 px-4 py-3 dark:border-amber-900/60 dark:bg-amber-950/20">
                                                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                                    <div>
                                                        <p class="text-sm font-medium text-amber-900 dark:text-amber-100">{{ __('Published final report awaiting response tracking') }}</p>
                                                        <flux:text class="mt-1 text-sm text-amber-800/90 dark:text-amber-200/90">
                                                            {{ __('Record whether government has been asked to respond, whether a reply arrived, or whether follow-up is overdue.') }}
                                                        </flux:text>
                                                    </div>

                                                    <flux:modal.trigger name="add-government-response">
                                                        <flux:button variant="primary" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate({{ $report->id }})">
                                                            {{ __('Track response') }}
                                                        </flux:button>
                                                    </flux:modal.trigger>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>

                    <flux:card class="space-y-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">{{ __('Government responses') }}</flux:heading>
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Track response requests, overdue follow-up, and formal replies against the report record they address.') }}
                                </flux:text>
                            </div>

                            @if ($review->reports->isNotEmpty())
                                <flux:modal.trigger name="add-government-response">
                                    <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareGovernmentResponseCreate">
                                        {{ __('Add response') }}
                                    </flux:button>
                                </flux:modal.trigger>
                            @endif
                        </div>

                        @if ($awaitingResponseReports->isNotEmpty())
                            <div class="space-y-3 rounded-xl border border-amber-200/80 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
                                <div class="space-y-1">
                                    <p class="text-sm font-medium text-amber-900 dark:text-amber-100">{{ __('Awaiting response on published final reports') }}</p>
                                    <flux:text class="text-sm text-amber-800/90 dark:text-amber-200/90">
                                        {{ __('These final reports have been published but do not yet have a response request, response received, or overdue record attached.') }}
                                    </flux:text>
                                </div>

                                <div class="space-y-2">
                                    @foreach ($awaitingResponseReports as $awaitingReport)
                                        <div class="flex flex-col gap-3 rounded-xl border border-amber-200/80 bg-white/80 px-4 py-3 dark:border-amber-900/40 dark:bg-zinc-950/40 sm:flex-row sm:items-center sm:justify-between">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $awaitingReport->title }}</p>
                                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                                    {{ __('Published :date', ['date' => $awaitingReport->published_at?->toFormattedDateString() ?? __('date not set')]) }}
                                                </flux:text>
                                            </div>

                                            <flux:modal.trigger name="add-government-response">
                                                <flux:button variant="primary" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate({{ $awaitingReport->id }})">
                                                    {{ __('Track response') }}
                                                </flux:button>
                                            </flux:modal.trigger>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if ($review->governmentResponses->isEmpty())
                            <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No government responses recorded yet.') }}
                                </flux:text>

                                <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                                    @if ($publishedFinalReports->isEmpty())
                                        {{ __('Once a final report is published, record whether government has been asked to respond and capture any reply here.') }}
                                    @else
                                        {{ __('A final report has been published. Add a response record when government is asked to reply, when a response arrives, or when follow-up becomes overdue.') }}
                                    @endif
                                </flux:text>
                            </div>
                        @else
                            <div class="divide-y divide-zinc-100 dark:divide-zinc-800/60">
                                @foreach ($review->governmentResponses->sortByDesc(fn ($response) => $response->received_at?->timestamp ?? $response->created_at?->timestamp ?? 0) as $response)
                                    @php
                                        $responseClasses = match ($response->response_status) {
                                            \App\Domain\Reporting\Enums\GovernmentResponseStatus::Received => 'border-emerald-200/80 bg-emerald-50/80 text-emerald-700 dark:border-emerald-900/60 dark:bg-emerald-950/20 dark:text-emerald-300',
                                            \App\Domain\Reporting\Enums\GovernmentResponseStatus::Overdue => 'border-rose-200/80 bg-rose-50/80 text-rose-700 dark:border-rose-900/60 dark:bg-rose-950/20 dark:text-rose-300',
                                            \App\Domain\Reporting\Enums\GovernmentResponseStatus::Requested => 'border-amber-200/80 bg-amber-50/70 text-amber-700 dark:border-amber-900/60 dark:bg-amber-950/15 dark:text-amber-300',
                                        };
                                    @endphp

                                    <div class="space-y-2 py-3 first:pt-0 last:pb-0">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $response->report?->title ?? __('Unknown report') }}</p>
                                                <div class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    <span>{{ $response->received_at?->toFormattedDateString() ?? __('No received date') }}</span>
                                                    @if ($response->document)
                                                        <span>{{ $response->document->title }}</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <span class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md border px-2.5 py-1 text-center text-[11px] font-medium leading-none {{ $responseClasses }}">
                                                {{ \Illuminate\Support\Str::headline($response->response_status->value) }}
                                            </span>
                                        </div>

                                        @if ($response->summary)
                                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">{{ $response->summary }}</flux:text>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </flux:card>
                </div>
            </div>

            {{-- Add report modal --}}
            <flux:modal name="add-report" class="md:w-[32rem]">
                <form wire:submit="storeReport" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Add report') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Create a report record, connect it to the linked publication file when available, and keep its status explicit.') }}</flux:text>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Drafting context') }}</flux:text>
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('This review currently has :findings findings and :recommendations recommendations that should inform report drafting.', [
                                'findings' => $review->findings->count(),
                                'recommendations' => $review->recommendations->count(),
                            ]) }}
                        </flux:text>

                        @if ($draftRecommendations->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-2">
                                @foreach ($draftRecommendations as $recommendation)
                                    <flux:badge size="sm">{{ $recommendation->title }}</flux:badge>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <flux:input wire:model="reportTitle" :invalid="$errors->has('reportTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportType" :invalid="$errors->has('reportType')" :label="__('Type')">
                            @foreach ($reportTypes as $type)
                                <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="reportStatus" :invalid="$errors->has('reportStatus')" :label="__('Status')">
                            @foreach ($reportStatuses as $status)
                                <flux:select.option :value="$status->value">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportDocumentId" :invalid="$errors->has('reportDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($preferredReportDocuments as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                            @foreach ($otherReportDocuments as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="reportPublishedAt" :invalid="$errors->has('reportPublishedAt')" :label="__('Published at')" type="date" />
                    </div>

                    @if ($selectedReportDocument)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Selected document') }}</flux:text>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $selectedReportDocument->title }}</span>
                                <flux:badge size="sm">{{ $this->documentTypeLabel($selectedReportDocument->document_type) }}</flux:badge>
                            </div>
                        </div>
                    @endif

                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('If the report is marked published and the date is blank, today is used automatically.') }}
                    </flux:text>

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="edit-report" class="md:w-[32rem]">
                <form wire:submit="updateReport" class="space-y-6">
                    <div>
                        <flux:heading size="lg">{{ __('Edit report') }}</flux:heading>
                        <flux:text class="mt-1">{{ __('Keep the report record aligned with its publication status, source document, and downstream response tracking.') }}</flux:text>
                    </div>

                    <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Drafting context') }}</flux:text>
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Use this record as the source of truth for whether the output is still drafting, published, or archived.') }}
                        </flux:text>
                    </div>

                    <flux:input wire:model="reportTitle" :invalid="$errors->has('reportTitle')" :label="__('Title')" />

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportType" :invalid="$errors->has('reportType')" :label="__('Type')">
                            @foreach ($reportTypes as $type)
                                <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:select wire:model="reportStatus" :invalid="$errors->has('reportStatus')" :label="__('Status')">
                            @foreach ($reportStatuses as $status)
                                <flux:select.option :value="$status->value">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:select wire:model="reportDocumentId" :invalid="$errors->has('reportDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($preferredReportDocuments as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                            @foreach ($otherReportDocuments as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:input wire:model="reportPublishedAt" :invalid="$errors->has('reportPublishedAt')" :label="__('Published at')" type="date" />
                    </div>

                    @if ($selectedReportDocument)
                        <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/60">
                            <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Selected document') }}</flux:text>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $selectedReportDocument->title }}</span>
                                <flux:badge size="sm">{{ $this->documentTypeLabel($selectedReportDocument->document_type) }}</flux:badge>
                            </div>
                        </div>
                    @endif

                    <div class="flex justify-end">
                        <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
                    </div>
                </form>
            </flux:modal>

            <flux:modal name="add-government-response" class="md:w-[34rem]">
                @if ($review->reports->isEmpty())
                    <div class="space-y-4">
                        <div>
                            <flux:heading size="lg">{{ __('Add government response') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Create a report record first so response tracking can stay attached to the review lifecycle.') }}</flux:text>
                        </div>

                        <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                            <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('No reports available yet') }}</p>
                            <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('Once at least one report is recorded, you can log response requests, received replies, and overdue follow-up here.') }}
                            </flux:text>
                        </div>
                    </div>
                @else
                    <form wire:submit="storeGovernmentResponse" class="space-y-6">
                        <div>
                            <flux:heading size="lg">{{ __('Add government response') }}</flux:heading>
                            <flux:text class="mt-1">{{ __('Record the response status for a report and capture the document or summary that came back from government.') }}</flux:text>
                        </div>

                        @if ($selectedGovernmentResponseReport)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-4 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Selected report') }}</flux:text>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $selectedGovernmentResponseReport->title }}</span>
                                    <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($selectedGovernmentResponseReport->report_type->value) }}</flux:badge>
                                    <flux:badge size="sm" color="zinc">{{ \Illuminate\Support\Str::headline($selectedGovernmentResponseReport->status->value) }}</flux:badge>
                                </div>

                                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                                    <div>
                                        <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Published') }}</flux:text>
                                        <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">{{ $selectedGovernmentResponseReport->published_at?->toFormattedDateString() ?? __('Not published') }}</p>
                                    </div>
                                    <div>
                                        <flux:text class="text-xs text-zinc-400 dark:text-zinc-500">{{ __('Latest response') }}</flux:text>
                                        <p class="mt-1 text-sm text-zinc-800 dark:text-zinc-200">
                                            {{ $selectedGovernmentResponseLatest ? \Illuminate\Support\Str::headline($selectedGovernmentResponseLatest->response_status->value) : __('No response tracked yet') }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <flux:select wire:model="governmentResponseReportId" :invalid="$errors->has('governmentResponseReportId')" :label="__('Report')" :placeholder="__('Select report')">
                            @foreach ($awaitingResponseReports as $reportOption)
                                <flux:select.option :value="$reportOption->id">{{ $reportOption->title }}</flux:select.option>
                            @endforeach
                            @foreach ($review->reports->reject(fn ($reportOption) => $awaitingResponseReports->contains('id', $reportOption->id)) as $reportOption)
                                <flux:select.option :value="$reportOption->id">{{ $reportOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <flux:select wire:model="governmentResponseStatus" :invalid="$errors->has('governmentResponseStatus')" :label="__('Response status')">
                                @foreach ($governmentResponseStatuses as $status)
                                    <flux:select.option :value="$status->value">{{ \Illuminate\Support\Str::headline($status->value) }}</flux:select.option>
                                @endforeach
                            </flux:select>

                            <flux:input wire:model="governmentResponseReceivedAt" :invalid="$errors->has('governmentResponseReceivedAt')" :label="__('Received at')" type="date" />
                        </div>

                        <flux:select wire:model="governmentResponseDocumentId" :invalid="$errors->has('governmentResponseDocumentId')" :label="__('Linked document')" :placeholder="__('None')">
                            @foreach ($preferredResponseDocuments as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                            @foreach ($otherResponseDocuments as $documentOption)
                                <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                            @endforeach
                        </flux:select>

                        @if ($selectedGovernmentResponseDocument)
                            <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/60">
                                <flux:text class="text-xs font-medium uppercase tracking-[0.16em] text-zinc-400 dark:text-zinc-500">{{ __('Selected document') }}</flux:text>
                                <div class="mt-2 flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $selectedGovernmentResponseDocument->title }}</span>
                                    <flux:badge size="sm">{{ $this->documentTypeLabel($selectedGovernmentResponseDocument->document_type) }}</flux:badge>
                                </div>
                            </div>
                        @endif

                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                            {{ __('Use "requested" when government has been asked to respond, "received" when a reply arrives, and "overdue" when the expected response has slipped.') }}
                        </flux:text>

                        <flux:textarea wire:model="governmentResponseSummary" :invalid="$errors->has('governmentResponseSummary')" :label="__('Summary')" rows="4" />

                        <div class="flex justify-end">
                            <flux:button variant="primary" type="submit">{{ __('Add response') }}</flux:button>
                        </div>
                    </form>
                @endif
            </flux:modal>
        </flux:tab.panel>
    </flux:tab.group>

    <flux:modal name="confirm-workspace-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
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
                        x-on:click="$wire.confirmDeletion(deleteConfirmation.type, deleteConfirmation.id); resetDeleteConfirmation()"
                        x-bind:disabled="! deleteConfirmation.type || ! deleteConfirmation.id"
                    >
                        <span x-text="`${@js(__('Delete'))} ${deleteConfirmation.noun || @js(__('record'))}`"></span>
                    </flux:button>
                </flux:modal.close>
            </div>
        </div>
    </flux:modal>
</div>
