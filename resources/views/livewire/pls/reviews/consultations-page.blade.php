<div class="space-y-8">
    <div class="grid gap-8 xl:grid-cols-1">
        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('Consultation plan') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Choose one or more ways to hear from people, then keep the planned activities and their results together.') }}
                    </flux:text>
                </div>

                <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareConsultationCreate">{{ __('Plan consultation') }}</flux:button>
            </div>

            <div class="space-y-5">
                @if ($consultations->isEmpty())
                    <div class="rounded-2xl border border-dashed border-zinc-300 bg-zinc-50/70 p-6 dark:border-zinc-700 dark:bg-zinc-900/40">
                        <flux:heading size="base">{{ __('Plan how you will hear from people') }}</flux:heading>
                        <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('Select the methods that fit this review: hearings, interviews, focus groups, surveys, written calls for evidence, public comment periods, roundtables, or workshops. Each method becomes a trackable consultation activity.') }}
                        </flux:text>
                        <div class="mt-4">
                            <flux:button variant="primary" icon="plus" wire:click="prepareConsultationCreate">{{ __('Plan the first consultation') }}</flux:button>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-between">
                        <flux:heading size="sm">{{ __('Planned and completed activities') }}</flux:heading>
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
                                            <flux:badge size="sm" color="zinc">{{ $this->consultationTypeLabel($consultation->consultation_type) }}</flux:badge>
                                            <flux:badge size="sm" :color="$consultationCompleted ? 'emerald' : 'amber'">
                                                {{ $consultationCompleted ? __('Completed') : __('Planned') }}
                                            </flux:badge>
                                        </div>

                                        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ $consultationCompleted ? $consultation->held_at?->toFormattedDateString() : __('Not scheduled') }}
                                        </flux:text>

                                        <flux:text class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $consultation->summary }}</flux:text>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1">
                                        <flux:button variant="subtle" size="sm" icon="arrow-up-tray" wire:click="prepareConsultationMaterialUpload({{ $consultation->id }})">
                                            {{ __('Add results') }}
                                        </flux:button>

                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingConsultation({{ $consultation->id }})">
                                            {{ __('Edit') }}
                                        </flux:button>
                                    </div>
                                </div>

                                @php($consultationInsight = $this->consultationInsight($consultation))

                                <div class="mt-4 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                                    <div class="flex flex-wrap items-center justify-between gap-3">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Results and evidence') }}</span>
                                            <flux:badge size="sm" color="zinc">{{ trans_choice('{0} no files|{1} :count file|[2,*] :count files', $consultationInsight['count'], ['count' => $consultationInsight['count']]) }}</flux:badge>
                                        </div>

                                        @if ($consultationInsight['analyzed_count'] > 0)
                                            <flux:button variant="ghost" size="sm" icon="sparkles" wire:click="askAssistantForConsultationInsights({{ $consultation->id }})">
                                                {{ __('Summarize results') }}
                                            </flux:button>
                                        @endif
                                    </div>

                                    @if ($consultation->materials->isEmpty())
                                        <flux:text class="mt-3 text-sm text-zinc-500 dark:text-zinc-400">
                                            {{ __('Add the hearing transcript, interview notes, survey results, public input, or other material when it becomes available.') }}
                                        </flux:text>
                                    @else
                                        <div class="mt-3 space-y-2">
                                            @foreach ($consultation->materials as $material)
                                                @php($materialStatus = data_get($material->document?->metadata, 'document_analysis.status'))
                                                <div class="flex flex-col gap-2 rounded-lg bg-zinc-50 px-3 py-2.5 sm:flex-row sm:items-start sm:justify-between dark:bg-zinc-900/60">
                                                    <div class="min-w-0">
                                                        <div class="flex flex-wrap items-center gap-2">
                                                            <span class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ $material->document?->title ?? __('Uploaded result') }}</span>
                                                            <flux:badge size="sm">{{ $this->consultationMaterialTypeLabel($material->material_type) }}</flux:badge>
                                                            @if ($material->stakeholder)
                                                                <span class="text-xs text-zinc-500 dark:text-zinc-400">{{ $material->stakeholder->name }}</span>
                                                            @endif
                                                        </div>

                                                        @if ($material->document?->summary)
                                                            <p class="mt-1 text-xs leading-5 text-zinc-600 dark:text-zinc-400">{{ $material->document->summary }}</p>
                                                        @elseif ($materialStatus === 'processing' || $materialStatus === 'queued')
                                                            <p class="mt-1 text-xs text-sky-700 dark:text-sky-300">{{ __('PLSAssist is reading this result.') }}</p>
                                                        @else
                                                            <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">{{ __('Waiting for a source summary.') }}</p>
                                                        @endif
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>

                                        @if ($consultationInsight['themes'] !== [])
                                            <div class="mt-4">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Emerging themes from uploaded results') }}</div>
                                                <div class="mt-2 flex flex-wrap gap-2">
                                                    @foreach ($consultationInsight['themes'] as $theme)
                                                        <button type="button" wire:click="askAssistantForConsultationInsights({{ $consultation->id }})" class="rounded-full bg-violet-50 px-2.5 py-1 text-xs font-medium text-violet-800 hover:bg-violet-100 dark:bg-violet-500/10 dark:text-violet-200 dark:hover:bg-violet-500/20">
                                                            {{ $theme }}
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endif

                                        @if ($consultationInsight['processing_count'] > 0)
                                            <flux:text class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ trans_choice('{1} One result is still being processed.|[2,*] :count results are still being processed.', $consultationInsight['processing_count'], ['count' => $consultationInsight['processing_count']]) }}
                                            </flux:text>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <div class="space-y-1">
                    <flux:heading size="lg">{{ __('Written submissions') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ __('Record evidence received directly from a stakeholder or organization.') }}</flux:text>
                </div>

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
                <flux:heading size="lg">{{ __('Plan consultation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Choose all of the methods that are appropriate. PLSAssist will create a separate activity for each one so you can record and compare the results.') }}</flux:text>
            </div>

            <flux:input wire:model="consultationTitle" :invalid="$errors->has('consultationTitle')" :label="__('Plan title')" :placeholder="__('For example: Hear from people affected by implementation')" />

            <flux:field>
                <flux:label>{{ __('Consultation methods') }}</flux:label>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach ($consultationTypes as $consultationTypeOption)
                        <label class="flex cursor-pointer items-center gap-3 rounded-lg border border-zinc-200 px-3 py-2.5 text-sm text-zinc-700 transition hover:border-violet-300 hover:bg-violet-50/60 dark:border-zinc-800 dark:text-zinc-200 dark:hover:border-violet-500/40 dark:hover:bg-violet-500/10">
                            <input type="checkbox" wire:model="consultationTypesToPlan" value="{{ $consultationTypeOption->value }}" class="rounded border-zinc-300 text-violet-700 focus:ring-violet-600 dark:border-zinc-700" />
                            <span>{{ $this->consultationTypeLabel($consultationTypeOption) }}</span>
                        </label>
                    @endforeach
                </div>
                <flux:error name="consultationTypesToPlan" />
            </flux:field>

            <flux:input wire:model="consultationHeldAt" :invalid="$errors->has('consultationHeldAt')" :label="__('Date scheduled or held')" type="date" />

            <flux:textarea
                wire:model="consultationSummary"
                :invalid="$errors->has('consultationSummary')"
                :label="__('Purpose and key questions')"
                :placeholder="__('What do you need to learn, and whose experience or evidence should this consultation bring into the review?')"
                rows="4"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Create consultation plan') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditConsultationModal" class="md:w-[34rem]">
        <form wire:submit="updateConsultation" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit consultation') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Update the activity, its purpose, or its schedule. Add transcripts and other results directly from the activity card.') }}</flux:text>
            </div>

            <flux:input wire:model="consultationTitle" :invalid="$errors->has('consultationTitle')" :label="__('Title')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="consultationType" :invalid="$errors->has('consultationType')" :label="__('Type')">
                    @foreach ($consultationTypes as $consultationTypeOption)
                        <flux:select.option :value="$consultationTypeOption->value">{{ $this->consultationTypeLabel($consultationTypeOption) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="consultationHeldAt" :invalid="$errors->has('consultationHeldAt')" :label="__('Date scheduled or held')" type="date" />
            </div>

            <flux:textarea
                wire:model="consultationSummary"
                :invalid="$errors->has('consultationSummary')"
                :label="__('Purpose or outcome note')"
                :placeholder="__('What does this activity need to explore, or what happened and why does it matter for the review?')"
                rows="4"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Save changes') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showAddConsultationMaterialModal" class="md:w-[38rem]">
        <form wire:submit="storeConsultationMaterial" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add consultation results') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Upload the transcript, notes, public input, survey results, or other record from this activity. PLSAssist will read the file and surface its source-grounded themes.') }}</flux:text>
            </div>

            <div x-data="{ uploading: false, progress: 0 }" x-on:livewire-upload-start="uploading = true; progress = 0" x-on:livewire-upload-finish="uploading = false; progress = 100" x-on:livewire-upload-cancel="uploading = false; progress = 0" x-on:livewire-upload-error="uploading = false" x-on:livewire-upload-progress="progress = $event.detail.progress" class="space-y-3">
                <flux:file-upload wire:model="consultationMaterialUpload" accept=".pdf,.docx,.txt,.md,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,text/plain,text/markdown" :label="__('Result file')">
                    <flux:file-upload.dropzone class="!min-h-28 !py-4" :heading="__('Drag a consultation result here or choose a file')" :text="__('PDF, DOCX, TXT, or MD, 50 MB max')" />
                </flux:file-upload>
                <flux:error name="consultationMaterialUpload" />

                <div x-cloak x-show="uploading" class="space-y-2">
                    <div class="flex items-center justify-between text-sm text-zinc-600 dark:text-zinc-300">
                        <span>{{ __('Uploading result') }}</span>
                        <span x-text="`${progress}%`"></span>
                    </div>
                    <flux:progress value="0" color="sky" x-bind:value="progress" />
                </div>
            </div>

            <flux:input wire:model="consultationMaterialTitle" :invalid="$errors->has('consultationMaterialTitle')" :label="__('Title (optional)')" :placeholder="__('Uses the filename if left blank')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="consultationMaterialType" :invalid="$errors->has('consultationMaterialType')" :label="__('Result type')">
                    @foreach ($consultationMaterialTypes as $materialTypeOption)
                        <flux:select.option :value="$materialTypeOption->value">{{ $this->consultationMaterialTypeLabel($materialTypeOption) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:select wire:model="consultationMaterialStakeholderId" :invalid="$errors->has('consultationMaterialStakeholderId')" :label="__('Related stakeholder (optional)')" :placeholder="__('None')">
                    @foreach ($review->stakeholders as $stakeholderOption)
                        <flux:select.option :value="$stakeholderOption->id">{{ $stakeholderOption->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" wire:loading.attr="disabled" wire:target="consultationMaterialUpload,storeConsultationMaterial">{{ __('Add results') }}</flux:button>
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
                        <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($selectedSubmissionStakeholder->stakeholder_type) }}</flux:badge>
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
