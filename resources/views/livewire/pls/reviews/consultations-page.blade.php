<div class="space-y-8">
    <flux:card class="space-y-6">
        <div>
            <flux:heading size="lg">{{ __('Consultation and evidence intake') }}</flux:heading>
            <flux:text class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                {{ __('Keep planned engagement, completed activity, and written evidence in one workspace so the review team can trace participation back to the workflow.') }}
            </flux:text>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Consultations held') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $completedConsultations->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Planned consultations') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $plannedConsultations->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Submissions received') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->submissions->count() }}</p>
            </div>
            <div class="flex flex-col rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/70">
                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Stakeholders with evidence') }}</flux:text>
                <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $stakeholdersWithSubmissions->count() }}</p>
            </div>
        </div>
    </flux:card>

    <div class="grid gap-8 xl:grid-cols-[minmax(0,1.2fr)_minmax(340px,0.95fr)]">
        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Consultation activity') }}</flux:heading>

                <flux:modal.trigger name="add-consultation">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareConsultationCreate">{{ __('Add') }}</flux:button>
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
                        <flux:heading size="sm">{{ __('Completed') }}</flux:heading>
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
                        <flux:heading size="sm">{{ __('Planned') }}</flux:heading>
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

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Submissions and evidence') }}</flux:heading>

                <flux:modal.trigger name="add-submission">
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareSubmissionCreate" :disabled="$review->stakeholders->isEmpty()">{{ __('Add') }}</flux:button>
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
                    @if ($selectedSubmissionStakeholder)
                        <div class="rounded-xl border border-violet-200 bg-violet-50/70 px-4 py-3 text-sm text-violet-900 dark:border-violet-900/60 dark:bg-violet-950/30 dark:text-violet-100">
                            {{ __('Submission handoff prepared for :name. Use “Add submission” to log evidence with this stakeholder preselected.', ['name' => $selectedSubmissionStakeholder->name]) }}
                        </div>
                    @endif

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
                            <flux:heading size="sm">{{ __('Received submissions') }}</flux:heading>
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
</div>
