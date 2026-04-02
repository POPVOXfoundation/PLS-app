<?php

namespace App\Livewire\Pls\Assistant;

use App\Domain\Assistant\AssistantTabPlaybook;
use App\Domain\Assistant\AssistantTabPlaybookVersion;
use App\Support\PlsAssistant\PlaybookRepository;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class TabPlaybooks extends Component
{
    use AuthorizesRequests;

    public string $selectedTabKey = 'workflow';

    public string $tabLabel = '';

    public string $role = '';

    public string $intro = '';

    public string $objectivesText = '';

    public string $allowedCapabilitiesText = '';

    public string $disallowedCapabilitiesText = '';

    public string $suggestedPromptsText = '';

    public string $rulesText = '';

    public string $guardrailsText = '';

    public string $responseStyleText = '';

    public string $changeNote = '';

    public string $editorSource = '';

    public function mount(): void
    {
        $this->authorize('manageAssistantPlaybooks');
        $this->loadEditorForTab($this->selectedTabKey);
    }

    public function render(): View
    {
        return view('livewire.pls.assistant.tab-playbooks', [
            'selectedPlaybookRecord' => $this->selectedPlaybookRecord(),
            'tabOptions' => $this->tabOptions(),
        ])->layout('layouts.app', [
            'title' => __('Assistant playbooks'),
        ]);
    }

    public function selectTab(string $tabKey): void
    {
        $this->authorize('manageAssistantPlaybooks');
        $this->loadEditorForTab($tabKey);
    }

    public function resetEditor(): void
    {
        $this->authorize('manageAssistantPlaybooks');
        $this->loadEditorForTab($this->selectedTabKey);
    }

    public function loadVersion(int $versionId): void
    {
        $this->authorize('manageAssistantPlaybooks');

        $playbook = $this->selectedPlaybookRecord();

        if ($playbook === null) {
            return;
        }

        $version = $playbook->versions->firstWhere('id', $versionId);

        if (! $version instanceof AssistantTabPlaybookVersion) {
            return;
        }

        $this->fillFromVersion($playbook, $version, sprintf('Version v%d', $version->version_number));
        $this->resetValidation();
    }

    public function saveVersion(): void
    {
        $this->authorize('manageAssistantPlaybooks');
        $this->validateEditor();

        DB::transaction(function (): void {
            $playbook = AssistantTabPlaybook::query()->firstOrCreate(
                ['tab_key' => $this->selectedTabKey],
                ['label' => trim($this->tabLabel)],
            );

            $playbook->forceFill([
                'label' => trim($this->tabLabel),
            ])->save();

            $version = $playbook->versions()->create([
                'version_number' => $playbook->nextVersionNumber(),
                'role' => trim($this->role),
                'intro' => trim($this->intro),
                'objectives' => $this->lineItems($this->objectivesText),
                'allowed_capabilities' => $this->lineItems($this->allowedCapabilitiesText),
                'disallowed_capabilities' => $this->lineItems($this->disallowedCapabilitiesText),
                'suggested_prompts' => $this->lineItems($this->suggestedPromptsText),
                'rules' => $this->lineItems($this->rulesText),
                'guardrails' => $this->lineItems($this->guardrailsText),
                'response_style' => $this->lineItems($this->responseStyleText),
                'change_note' => trim($this->changeNote),
                'created_by' => auth()->id(),
            ]);

            $playbook->forceFill([
                'active_version_id' => $version->id,
            ])->save();
        });

        $this->loadEditorForTab($this->selectedTabKey);

        $this->dispatch('app-toast', ...Toast::success(
            __('Playbook version saved'),
            __('The new version is now active for this tab.'),
        ));
    }

    public function activateVersion(int $versionId): void
    {
        $this->authorize('manageAssistantPlaybooks');

        $playbook = $this->selectedPlaybookRecord();

        if ($playbook === null) {
            return;
        }

        $version = $playbook->versions->firstWhere('id', $versionId);

        if (! $version instanceof AssistantTabPlaybookVersion) {
            return;
        }

        $playbook->forceFill([
            'active_version_id' => $version->id,
        ])->save();

        $this->loadEditorForTab($playbook->tab_key);

        $this->dispatch('app-toast', ...Toast::success(
            __('Playbook activated'),
            __('The selected version is now active for this tab.'),
        ));
    }

    /**
     * @return array<int, array{
     *     active_version_label: string,
     *     has_db_record: bool,
     *     key: string,
     *     label: string,
     *     versions_count: int
     * }>
     */
    private function tabOptions(): array
    {
        $workspaceKeys = $this->playbooks()->supportedWorkspaceKeys();
        $records = AssistantTabPlaybook::query()
            ->with('activeVersion')
            ->whereIn('tab_key', $workspaceKeys)
            ->get()
            ->keyBy('tab_key');

        return collect($workspaceKeys)
            ->map(function (string $tabKey) use ($records): array {
                /** @var AssistantTabPlaybook|null $record */
                $record = $records->get($tabKey);

                return [
                    'key' => $tabKey,
                    'label' => $record?->label ?: $this->playbooks()->workspaceLabel($tabKey),
                    'has_db_record' => $record !== null,
                    'active_version_label' => $record?->activeVersion !== null
                        ? sprintf('v%d', $record->activeVersion->version_number)
                        : __('Config fallback'),
                    'versions_count' => $record?->versions()->count() ?? 0,
                ];
            })
            ->all();
    }

    private function selectedPlaybookRecord(): ?AssistantTabPlaybook
    {
        return AssistantTabPlaybook::query()
            ->with(['activeVersion', 'versions.createdBy'])
            ->where('tab_key', $this->selectedTabKey)
            ->first();
    }

    private function loadEditorForTab(string $tabKey): void
    {
        $resolvedTabKey = $this->playbooks()->resolveWorkspaceKey($tabKey);
        $this->selectedTabKey = $resolvedTabKey;

        $playbook = AssistantTabPlaybook::query()
            ->with(['activeVersion', 'versions.createdBy'])
            ->where('tab_key', $resolvedTabKey)
            ->first();

        if ($playbook?->activeVersion !== null) {
            $this->fillFromVersion(
                $playbook,
                $playbook->activeVersion,
                sprintf('Active version v%d', $playbook->activeVersion->version_number),
            );

            return;
        }

        $this->tabLabel = $this->playbooks()->workspaceLabel($resolvedTabKey);
        $this->fillFromArray(
            $this->playbooks()->tab($resolvedTabKey),
            __('Config fallback'),
        );
    }

    private function fillFromVersion(
        AssistantTabPlaybook $playbook,
        AssistantTabPlaybookVersion $version,
        string $sourceLabel,
    ): void {
        $this->tabLabel = $playbook->label;
        $this->fillFromArray([
            'role' => $version->role,
            'intro' => $version->intro,
            'objectives' => $version->objectives,
            'allowed_capabilities' => $version->allowed_capabilities,
            'disallowed_capabilities' => $version->disallowed_capabilities,
            'suggested_prompts' => $version->suggested_prompts,
            'rules' => $version->rules,
            'guardrails' => $version->guardrails,
            'response_style' => $version->response_style,
        ], $sourceLabel);
    }

    /**
     * @param  array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     suggested_prompts: list<string>
     * }  $playbook
     */
    private function fillFromArray(array $playbook, string $sourceLabel): void
    {
        $this->role = $playbook['role'];
        $this->intro = $playbook['intro'];
        $this->objectivesText = $this->stringifyLines($playbook['objectives']);
        $this->allowedCapabilitiesText = $this->stringifyLines($playbook['allowed_capabilities']);
        $this->disallowedCapabilitiesText = $this->stringifyLines($playbook['disallowed_capabilities']);
        $this->suggestedPromptsText = $this->stringifyLines($playbook['suggested_prompts']);
        $this->rulesText = $this->stringifyLines($playbook['rules']);
        $this->guardrailsText = $this->stringifyLines($playbook['guardrails']);
        $this->responseStyleText = $this->stringifyLines($playbook['response_style']);
        $this->changeNote = '';
        $this->editorSource = $sourceLabel;
    }

    private function validateEditor(): void
    {
        validator(
            [
                'tabLabel' => $this->tabLabel,
                'role' => $this->role,
                'intro' => $this->intro,
                'objectivesText' => $this->objectivesText,
                'allowedCapabilitiesText' => $this->allowedCapabilitiesText,
                'suggestedPromptsText' => $this->suggestedPromptsText,
                'rulesText' => $this->rulesText,
                'guardrailsText' => $this->guardrailsText,
                'responseStyleText' => $this->responseStyleText,
                'changeNote' => $this->changeNote,
            ],
            [
                'tabLabel' => ['required', 'string', 'max:255'],
                'role' => ['required', 'string'],
                'intro' => ['required', 'string'],
                'objectivesText' => ['required', 'string'],
                'allowedCapabilitiesText' => ['required', 'string'],
                'suggestedPromptsText' => ['required', 'string'],
                'rulesText' => ['required', 'string'],
                'guardrailsText' => ['required', 'string'],
                'responseStyleText' => ['required', 'string'],
                'changeNote' => ['required', 'string'],
            ],
        )->after(function ($validator): void {
            collect([
                'objectivesText' => $this->objectivesText,
                'allowedCapabilitiesText' => $this->allowedCapabilitiesText,
                'suggestedPromptsText' => $this->suggestedPromptsText,
                'rulesText' => $this->rulesText,
                'guardrailsText' => $this->guardrailsText,
                'responseStyleText' => $this->responseStyleText,
            ])->each(function (string $value, string $field) use ($validator): void {
                if ($this->lineItems($value) === []) {
                    $validator->errors()->add($field, __('Enter at least one line.'));
                }
            });
        })->validate();
    }

    /**
     * @return list<string>
     */
    private function lineItems(string $value): array
    {
        return collect(preg_split('/\r\n|\r|\n/', $value) ?: [])
            ->map(fn (string $line): string => trim($line))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $value
     */
    private function stringifyLines(array $value): string
    {
        return implode(PHP_EOL, $value);
    }

    private function playbooks(): PlaybookRepository
    {
        return app(PlaybookRepository::class);
    }
}
