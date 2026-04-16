<div class="space-y-8">
    <div class="grid gap-8 xl:grid-cols-1">
        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Consultation activity') }}</flux:heading>

                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareConsultationCreate">{{ __('Add') }}</flux:button>
            </div>

            <div class="space-y-5">
                @if ($consultations->isEmpty())
                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="base">{{ __('No consultation activity recorded yet') }}</flux:heading>
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Use this area for both planned outreach and completed hearings, interviews, roundtables, or public consultations.') }}
                        </flux:text>
                        <div class="mt-4">
                            <flux:button variant="primary" icon="plus" wire:click="prepareConsultationCreate">{{ __('Add the first consultation') }}</flux:button>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Records') }}</flux:heading>
                        <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $consultations->count() }}</span>
                    </div>

                    <div class="space-y-3">
                        @foreach ($consultations as $consultation)
                            @php($consultationCompleted = $consultation->held_at !== null)

                            <div wire:key="consultation-{{ $consultation->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 space-y-2">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $consultation->title }}</p>
                                            <flux:badge size="sm" color="zinc">{{ \Illuminate\Support\Str::headline($consultation->consultation_type->value) }}</flux:badge>
                                            <flux:badge size="sm" :color="$consultationCompleted ? 'emerald' : 'amber'">
                                                {{ $consultationCompleted ? __('Completed') : __('Planned') }}
                                            </flux:badge>
                                        </div>

                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $consultationCompleted ? $consultation->held_at?->toFormattedDateString() : __('Not scheduled') }}
                                        </flux:text>
                                    </div>

                                    <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingConsultation({{ $consultation->id }})">
                                        {{ __('Edit') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Written submissions') }}</flux:heading>

                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareSubmissionCreate" :disabled="$review->stakeholders->isEmpty()">{{ __('Add') }}</flux:button>
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
                    <div class="space-y-3">
                        @if ($review->submissions->isEmpty())
                            <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                                <flux:heading size="base">{{ __('No submissions logged yet') }}</flux:heading>
                                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                                    {{ __('Once written evidence starts arriving, log it here and keep it tied to the stakeholder record that supplied it.') }}
                                </flux:text>
                                <div class="mt-4">
                                    <flux:button variant="primary" icon="plus" wire:click="prepareSubmissionCreate">{{ __('Add the first submission') }}</flux:button>
                                </div>
                            </div>
                        @else
                            <div class="flex items-center justify-between">
                                <flux:heading size="sm">{{ __('Records') }}</flux:heading>
                                <span class="text-xs tabular-nums text-zinc-400 dark:text-zinc-500">{{ $review->submissions->count() }}</span>
                            </div>

                            <div class="space-y-3">
                                @foreach ($review->submissions->sortByDesc(fn ($submission) => $submission->submitted_at?->timestamp ?? $submission->created_at?->timestamp ?? 0) as $submission)
                                    <div wire:key="submission-{{ $submission->id }}" class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0 space-y-2">
                                                <p class="truncate text-sm font-medium text-zinc-900 dark:text-white">{{ $submission->stakeholder?->name ?? __('Unknown stakeholder') }}</p>
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

    <flux:modal wire:model.self="showAddConsultationModal" class="md:w-[34rem]">
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

            <flux:select
                wire:model="consultationDocumentId"
                :invalid="$errors->has('consultationDocumentId')"
                :label="__('Linked document')"
                :placeholder="__('None')"
            >
                @foreach ($availableDocuments as $documentOption)
                    <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="consultationSummary"
                :invalid="$errors->has('consultationSummary')"
                :label="__('Consultation note')"
                :placeholder="__('What happened in this consultation, and why does it matter for the review?')"
                rows="4"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add consultation') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditConsultationModal" class="md:w-[34rem]">
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

            <flux:select
                wire:model="consultationDocumentId"
                :invalid="$errors->has('consultationDocumentId')"
                :label="__('Linked document')"
                :placeholder="__('None')"
            >
                @foreach ($availableDocuments as $documentOption)
                    <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:textarea
                wire:model="consultationSummary"
                :invalid="$errors->has('consultationSummary')"
                :label="__('Consultation note')"
                :placeholder="__('What happened in this consultation, and why does it matter for the review?')"
                rows="4"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showAddSubmissionModal" class="md:w-[34rem]">
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
                <flux:select
                    wire:model.live="submissionDocumentId"
                    :invalid="$errors->has('submissionDocumentId')"
                    :label="__('Linked document')"
                    :placeholder="__('None')"
                >
                    @foreach ($availableDocuments as $documentOption)
                        <flux:select.option :value="$documentOption->id">{{ $documentOption->title }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <flux:textarea
                wire:model="submissionSummary"
                :invalid="$errors->has('submissionSummary')"
                :label="__('Review note')"
                :placeholder="__('Why does this submission matter for the review?')"
                rows="4"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add submission') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
