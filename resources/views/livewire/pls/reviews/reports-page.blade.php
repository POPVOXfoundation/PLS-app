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

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Reporting workspace') }}</flux:heading>

                <div class="flex flex-wrap gap-2">
                    <flux:modal.trigger name="add-report">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="document-text"
                            wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::DraftReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                        >
                            {{ __('Draft') }}
                        </flux:button>
                    </flux:modal.trigger>

                    <flux:modal.trigger name="add-report">
                        <flux:button
                            variant="ghost"
                            size="sm"
                            icon="clipboard-document-list"
                            wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::FinalReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                        >
                            {{ __('Final') }}
                        </flux:button>
                    </flux:modal.trigger>

                    @if ($review->reports->isNotEmpty())
                        <flux:modal.trigger name="add-government-response">
                            <flux:button variant="primary" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate">
                                {{ __('Response') }}
                            </flux:button>
                        </flux:modal.trigger>
                    @endif
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Analysis ready') }}</flux:text>
                    <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->findings->count() }}/{{ $review->recommendations->count() }}</p>
                </div>
                <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Report outputs') }}</flux:text>
                    <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->reports->count() }}</p>
                </div>
                <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Awaiting follow-up') }}</flux:text>
                    <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $awaitingResponseReports->count() }}</p>
                </div>
                <div class="flex flex-col rounded-xl border border-zinc-200 p-5 dark:border-zinc-800">
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">{{ __('Responses captured') }}</flux:text>
                    <p class="mt-auto pt-3 text-2xl font-semibold tabular-nums text-zinc-950 dark:text-white">{{ $review->governmentResponses->count() }}</p>
                </div>
            </div>

            <div class="grid gap-4 xl:grid-cols-[minmax(0,1.15fr)_minmax(320px,0.95fr)]">
                <div class="rounded-xl border border-zinc-200 bg-zinc-50/70 p-5 dark:border-zinc-800 dark:bg-zinc-900/60">
                    <div class="space-y-2">
                        <flux:heading size="sm">{{ __('Drafting inputs from analysis') }}</flux:heading>
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

                <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950/40">
                    <div class="space-y-2">
                        <flux:heading size="sm">{{ __('Lifecycle view') }}</flux:heading>
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
                            <flux:badge size="sm">
                                {{ $review->recommendations->isNotEmpty() ? __('Ready') : __('Pending') }}
                            </flux:badge>
                        </div>

                        <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('2. Draft and final outputs') }}</p>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Create draft, final, briefing, or summary outputs and keep the linked publication document current.') }}
                                </flux:text>
                            </div>
                            <flux:badge size="sm">
                                {{ trans_choice(':count report|:count reports', $review->reports->count(), ['count' => $review->reports->count()]) }}
                            </flux:badge>
                        </div>

                        <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('3. Publication status') }}</p>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Published status and publication dates should be explicit so response obligations are visible.') }}
                                </flux:text>
                            </div>
                            <flux:badge size="sm">
                                {{ trans_choice(':count published|:count published', $publishedReportCount, ['count' => $publishedReportCount]) }}
                            </flux:badge>
                        </div>

                        <div class="flex items-start justify-between gap-3 rounded-xl border border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <div>
                                <p class="text-sm font-medium text-zinc-900 dark:text-white">{{ __('4. Government follow-up') }}</p>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('Requests, received replies, and overdue follow-up stay attached to the final report they address.') }}
                                </flux:text>
                            </div>
                            <flux:badge size="sm">
                                {{ $awaitingResponseReports->isNotEmpty() ? __('Action needed') : __('Tracked') }}
                            </flux:badge>
                        </div>
                    </div>
                </div>
            </div>
        </flux:card>

        <flux:card class="space-y-6">
            <div class="flex items-center justify-between gap-4">
                <flux:heading size="lg">{{ __('Reports') }}</flux:heading>

                <flux:modal.trigger name="add-report">
                    <flux:button
                        variant="primary"
                        size="sm"
                        icon="plus"
                        wire:click='prepareReportCreate(@js(\App\Domain\Reporting\Enums\ReportType::DraftReport->value), @js(\App\Domain\Reporting\Enums\ReportStatus::Draft->value))'
                    >
                        {{ __('Add') }}
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
                                            <flux:modal.trigger name="add-government-response">
                                                <flux:button variant="ghost" size="sm" icon="chat-bubble-left-right" wire:click="prepareGovernmentResponseCreate({{ $report->id }})" />
                                            </flux:modal.trigger>
                                        @endif

                                        <flux:modal.trigger name="edit-report">
                                            <flux:button variant="ghost" size="sm" icon="pencil-square" wire:click="startEditingReport({{ $report->id }})" />
                                        </flux:modal.trigger>

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
                    <flux:modal.trigger name="add-government-response">
                        <flux:button variant="primary" size="sm" icon="plus" wire:click="prepareGovernmentResponseCreate">
                            {{ __('Add') }}
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
                            <flux:badge size="sm">{{ \Illuminate\Support\Str::headline($selectedGovernmentResponseReport->status->value) }}</flux:badge>
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

    <flux:modal name="confirm-report-delete" x-on:close="resetDeleteConfirmation()" x-on:cancel="resetDeleteConfirmation()" class="max-w-lg">
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
