<div class="grid gap-8 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.9fr)]">
    <flux:card class="space-y-8">
        <div class="space-y-4">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Stakeholder directory') }}</flux:heading>

                <flux:modal.trigger name="add-stakeholder">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareStakeholderCreate">{{ __('Add') }}</flux:button>
                </flux:modal.trigger>
            </div>

            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Keep the people and organizations involved in the review current, searchable, and ready for evidence intake.') }}
            </flux:text>

            <flux:select wire:model.live="stakeholderTypeFilter" size="sm" class="max-w-48">
                <flux:select.option value="all">{{ __('All types') }}</flux:select.option>
                @foreach ($stakeholderTypes as $stakeholderTypeOption)
                    <flux:select.option :value="$stakeholderTypeOption->value">{{ \Illuminate\Support\Str::headline($stakeholderTypeOption->value) }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Stakeholders mapped') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->stakeholders->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('With submissions') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $stakeholdersWithSubmissions->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Missing contact detail') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $stakeholdersMissingContacts->count() }}</p>
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
            <div class="space-y-5">
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

                    <div class="rounded-2xl border border-zinc-200 p-6 dark:border-zinc-800">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-center gap-2">
                                <flux:heading size="base">{{ $stakeholder->name }}</flux:heading>
                                <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($stakeholder->stakeholder_type->value) }}</flux:badge>
                                @if ($stakeholder->submissions->isNotEmpty())
                                    <flux:badge size="sm" color="emerald">{{ __('Evidence received') }}</flux:badge>
                                @else
                                    <flux:badge size="sm" color="zinc">{{ __('Awaiting evidence') }}</flux:badge>
                                @endif
                            </div>

                            @if ($latestSubmission)
                                <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ $latestSubmission->summary }}
                                    <span class="text-zinc-400 dark:text-zinc-500">
                                        · {{ $latestSubmission->submitted_at?->toFormattedDateString() ?? __('Undated') }}@if ($latestSubmission->document) · {{ $latestSubmission->document->title }}@endif
                                    </span>
                                </flux:text>
                            @endif

                            <div class="flex flex-wrap items-center gap-3">
                                <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">
                                    {{ trans_choice('{0} 0 submissions|{1} 1 submission|[2,*] :count submissions', $stakeholder->submissions->count(), ['count' => $stakeholder->submissions->count()]) }}
                                </span>

                                <flux:modal.trigger name="edit-stakeholder">
                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingStakeholder({{ $stakeholder->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </flux:modal.trigger>

                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    icon="document-plus"
                                    :href="route('pls.reviews.consultations', ['review' => $review, 'stakeholder' => $stakeholder->id])"
                                    wire:navigate
                                >
                                    {{ __('Add submission') }}
                                </flux:button>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </flux:card>

    <div class="space-y-8">
        <flux:card class="space-y-5">
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
                <div class="space-y-5">
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

                                        <flux:button
                                            variant="ghost"
                                            size="sm"
                                            icon="document-plus"
                                            :href="route('pls.reviews.consultations', ['review' => $review, 'stakeholder' => $stakeholder->id])"
                                            wire:navigate
                                        >
                                            {{ __('Log evidence') }}
                                        </flux:button>
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

        <flux:card class="space-y-5">
            <div class="flex items-start justify-between gap-3">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('Implementing agencies') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Keep the implementation-review step grounded in the institutions responsible for delivery and oversight.') }}
                    </flux:text>
                </div>

                <flux:modal.trigger name="add-implementing-agency">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareImplementingAgencyCreate">{{ __('Add') }}</flux:button>
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
</div>
