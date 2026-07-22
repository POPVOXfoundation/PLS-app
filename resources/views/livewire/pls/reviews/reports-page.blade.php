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
>
    <div class="space-y-8">
        @if ($reportWorkflowFocus)
            <flux:card class="space-y-2">
                <flux:heading size="sm">{{ $reportWorkflowFocus['label'] }}</flux:heading>
                <flux:heading size="lg">{{ $reportWorkflowFocus['title'] }}</flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ $reportWorkflowFocus['summary'] }}
                </flux:text>
            </flux:card>
        @endif

        <flux:card class="space-y-5">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="lg">{{ __('Live report preview') }}</flux:heading>
                        <flux:badge size="sm" color="violet">{{ __('Working draft') }}</flux:badge>
                    </div>
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">{{ $reportPreview['detail'] }}</flux:text>
                </div>
                <flux:text class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $reportPreview['title'] }}</flux:text>
            </div>

            <div class="divide-y divide-zinc-200 border-y border-zinc-200 dark:divide-zinc-800 dark:border-zinc-800">
                @foreach ($reportPreview['sections'] as $section)
                    <div class="grid gap-3 py-4 lg:grid-cols-[11rem_minmax(0,1fr)_auto] lg:items-start lg:gap-5">
                        <div class="flex items-center gap-2">
                            <span @class([
                                'flex size-5 shrink-0 items-center justify-center rounded-full',
                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' => $section['ready'],
                                'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' => ! $section['ready'],
                            ])>
                                <flux:icon :icon="$section['ready'] ? 'check' : 'minus'" class="size-3" />
                            </span>
                            <span class="text-sm font-semibold text-zinc-900 dark:text-white">{{ $section['label'] }}</span>
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-zinc-800 dark:text-zinc-200">{{ $section['title'] }}</p>
                            <p class="mt-1 text-sm leading-6 text-zinc-600 dark:text-zinc-400">{{ $section['detail'] }}</p>
                        </div>
                        <a href="{{ $section['route'] }}" wire:navigate class="shrink-0 text-sm font-medium text-violet-700 hover:text-violet-900 dark:text-violet-300 dark:hover:text-violet-100">{{ $section['action'] }}</a>
                    </div>
                @endforeach
            </div>
        </flux:card>

        <flux:card class="space-y-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div class="max-w-2xl space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="lg">{{ __('Draft with PLSAssist') }}</flux:heading>
                        <flux:badge size="sm" color="amber">{{ __('Human review required') }}</flux:badge>
                    </div>
                    <flux:text class="text-sm leading-6 text-zinc-600 dark:text-zinc-400">
                        {{ __('Build report drafts from the review scope and confirmed analysis. PLSAssist keeps all drafting in the assistant so the review team can check the wording and sources before adding it to a working report.') }}
                    </flux:text>
                </div>

                <div class="flex shrink-0 flex-wrap gap-2">
                    <flux:button variant="primary" size="sm" icon="sparkles" wire:click="requestReportOutline">
                        {{ __('Build report outline') }}
                    </flux:button>
                    <flux:button variant="filled" size="sm" icon="pencil-square" wire:click="prepareReportDraft">
                        {{ __('Draft a section') }}
                    </flux:button>
                </div>
            </div>

            <div class="flex flex-wrap gap-2 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                <flux:button variant="ghost" size="sm" icon="document-text" wire:click="requestFindingsSectionDraft" :disabled="$review->findings->isEmpty()">
                    {{ __('Draft findings section') }}
                </flux:button>
                <flux:button variant="ghost" size="sm" icon="clipboard-document-check" wire:click="requestReportCoverageCheck">
                    {{ __('Check report coverage') }}
                </flux:button>
                @if ($review->findings->isEmpty())
                    <flux:text class="self-center text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Confirm findings first to draft a findings section.') }}
                    </flux:text>
                @endif
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Reports') }}</flux:heading>

                <div class="flex flex-wrap gap-2">
                    <flux:button
                        variant="primary"
                        size="sm"
                        icon="plus"
                        wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::DraftReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                    >
                        {{ __('Add') }}
                    </flux:button>

                    @if ($review->reports->isNotEmpty())
                        <flux:button variant="ghost" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate">
                            {{ __('Response') }}
                        </flux:button>
                    @endif
                </div>
            </div>

            @if ($review->recommendations->isNotEmpty())
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    {{ __('This review has :findings findings and :recommendations recommendations available as drafting inputs.', [
                        'findings' => $review->findings->count(),
                        'recommendations' => $review->recommendations->count(),
                    ]) }}
                </flux:text>
            @endif

            @if ($review->reports->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('No report records created yet. Start by creating a draft or final report record.') }}
                    </flux:text>
                </div>
            @else
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Report') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Published') }}</flux:table.column>
                        <flux:table.column>{{ __('Response') }}</flux:table.column>
                        <flux:table.column align="end"></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($review->reports->sortByDesc(fn ($report) => $report->published_at?->timestamp ?? $report->created_at?->timestamp ?? 0) as $report)
                            @php
                                $responseIndicator = $this->reportResponseIndicator($report);
                                $latestResponse = $this->latestGovernmentResponseForReport($report);
                            @endphp

                            <flux:table.row :key="$report->id">
                                <flux:table.cell variant="strong">
                                    <div class="flex items-center gap-2">
                                        <span>{{ $report->title }}</span>
                                        <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($report->report_type->value) }}</flux:badge>
                                    </div>
                                    @if ($report->document)
                                        <flux:text class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $report->document->title }}</flux:text>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">
                                        {{ \Illuminate\Support\Str::headline($report->status->value) }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>{{ $report->published_at?->toFormattedDateString() ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    <flux:badge size="sm">
                                        {{ $responseIndicator['label'] }}
                                    </flux:badge>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <div class="flex justify-end gap-1">
                                        @if ($report->report_type === \App\Domain\Reporting\Enums\ReportType::FinalReport && $report->status === \App\Domain\Reporting\Enums\ReportStatus::Published)
                                            <flux:button variant="ghost" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate({{ $report->id }})" />
                                        @endif

                                        <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingReport({{ $report->id }})" />

                                        <flux:modal.trigger name="confirm-report-delete">
                                            <flux:button
                                                variant="ghost"
                                                size="sm"
                                                icon="trash"
                                                :loading="false"
                                                x-on:click="setDeleteConfirmation('report', {{ $report->id }}, @js($report->title), @js(__('report')))"
                                            />
                                        </flux:modal.trigger>
                                    </div>
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            @endif
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Government responses') }}</flux:heading>

                @if ($review->reports->isNotEmpty())
                    <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareGovernmentResponseCreate">
                        {{ __('Add') }}
                    </flux:button>
                @endif
            </div>

            @if ($awaitingResponseReports->isNotEmpty())
                <div class="space-y-3 rounded-xl border border-amber-200/80 bg-amber-50/60 p-4 dark:border-amber-900/60 dark:bg-amber-950/20">
                    <p class="text-sm font-medium text-amber-900 dark:text-amber-100">{{ __('Awaiting response on published final reports') }}</p>

                    <div class="space-y-2">
                        @foreach ($awaitingResponseReports as $awaitingReport)
                            <div class="flex flex-col gap-3 rounded-xl border border-amber-200/80 bg-white/80 px-4 py-3 dark:border-amber-900/40 dark:bg-zinc-950/40 sm:flex-row sm:items-center sm:justify-between">
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $awaitingReport->title }}</p>
                                    <flux:text class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Published :date', ['date' => $awaitingReport->published_at?->toFormattedDateString() ?? __('date not set')]) }}
                                    </flux:text>
                                </div>

                                <flux:button variant="primary" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate({{ $awaitingReport->id }})">
                                    {{ __('Track response') }}
                                </flux:button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($review->governmentResponses->isEmpty())
                <div class="rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-800">
                    <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                        @if ($publishedFinalReports->isEmpty())
                            {{ __('No government responses recorded yet. Once a final report is published, track whether government has been asked to respond.') }}
                        @else
                            {{ __('A final report has been published. Add a response record when government is asked to reply, when a response arrives, or when follow-up becomes overdue.') }}
                        @endif
                    </flux:text>
                </div>
            @else
                <div class="space-y-3">
                    @foreach ($review->governmentResponses->sortByDesc(fn ($response) => $response->received_at?->timestamp ?? $response->created_at?->timestamp ?? 0) as $response)
                        <div class="rounded-xl border border-zinc-200 p-4 dark:border-zinc-800">
                            <div class="flex items-start justify-between gap-3">
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ $response->report?->title ?? __('Unknown report') }}</p>
                                <flux:badge size="sm">
                                    {{ \Illuminate\Support\Str::headline($response->response_status->value) }}
                                </flux:badge>
                            </div>

                            <flux:text class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ $response->received_at?->toFormattedDateString() ?? __('No received date') }}
                            </flux:text>

                            @if ($response->summary)
                                <flux:text class="mt-2 text-sm text-zinc-600 dark:text-zinc-400">{{ $response->summary }}</flux:text>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </flux:card>
    </div>

    <flux:modal wire:model.self="showReportDraftModal" class="md:w-[34rem]">
        <form wire:submit="developReportDraft" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Draft a report section') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Describe the section, audience, or question you need to address. PLSAssist will provide a draft tied to the confirmed review record and flag any limitations for the team to check.') }}</flux:text>
            </div>

            <flux:textarea
                wire:model="reportDraftRequest"
                :invalid="$errors->has('reportDraftRequest')"
                :label="__('Drafting task')"
                :placeholder="__('For example: Draft a concise executive summary for committee members that explains the strongest confirmed findings and the recommended next steps.')"
                rows="6"
            />

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit" icon="sparkles">{{ __('Draft with PLSAssist') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showAddReportModal" class="md:w-[32rem]">
        <form wire:submit="storeReport" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Add report') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Create a report record and link the publication document when available.') }}</flux:text>
            </div>

            <flux:input wire:model="reportTitle" :invalid="$errors->has('reportTitle')" :label="__('Title')" />

            <flux:select wire:model="reportType" :invalid="$errors->has('reportType')" :label="__('Type')">
                @foreach ($reportTypes as $type)
                    <flux:select.option :value="$type->value">{{ \Illuminate\Support\Str::headline($type->value) }}</flux:select.option>
                @endforeach
            </flux:select>

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
                {{ __('Reports start as drafts. Set a published date to mark them as published, or change the status later.') }}
            </flux:text>

            <div class="flex justify-end">
                <flux:button variant="primary" type="submit">{{ __('Add') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal wire:model.self="showEditReportModal" class="md:w-[32rem]">
        <form wire:submit="updateReport" class="space-y-6">
            <div>
                <flux:heading size="lg">{{ __('Edit report') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Keep the report record aligned with its publication status and linked document.') }}</flux:text>
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

    <flux:modal wire:model.self="showAddGovernmentResponseModal" class="md:w-[34rem]">
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
                            <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($selectedGovernmentResponseReport->status->value) }}</flux:badge>
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

    <flux:modal name="confirm-report-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
        <div class="space-y-6">
            <div class="space-y-2">
                <flux:heading size="lg" x-text="`${@js(__('Delete this'))} ${deleteConfirmation.noun || @js(__('record'))}?`"></flux:heading>
                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                    <span
                        x-show="deleteConfirmation.title"
                        x-text="`${@js(__('This will permanently remove'))} &quot;${deleteConfirmation.title}&quot; ${@js(__('from the review workspace.'))}`"
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
