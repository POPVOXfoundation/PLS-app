<?php

namespace App\Support\PlsAssistant;

use App\Domain\Assistant\AssistantTabPlaybook;
use App\Domain\Assistant\AssistantTabPlaybookVersion;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PlaybookRepository
{
    /**
     * @var array<string, AssistantTabPlaybook|null>
     */
    protected array $dbPlaybooks = [];

    /**
     * @return list<string>
     */
    public function systemRules(): array
    {
        return config('pls_assistant.system_rules', []);
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     suggested_prompts: list<string>
     * }
     */
    public function tab(string $workspaceKey): array
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);

        $dbPlaybook = $this->dbPlaybook($workspaceKey);

        if ($dbPlaybook?->activeVersion !== null) {
            return $this->normalizeVersion($dbPlaybook->activeVersion);
        }

        return $this->normalizeConfigTab($workspaceKey);
    }

    public function versionForTab(string $workspaceKey): string
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);
        $dbPlaybook = $this->dbPlaybook($workspaceKey);

        if ($dbPlaybook?->activeVersion !== null) {
            return sprintf(
                'db:v%d:%s',
                $dbPlaybook->activeVersion->version_number,
                $workspaceKey,
            );
        }

        $baseVersion = (string) config('pls_assistant.version', 'v1');
        $signature = json_encode([
            'system_rules' => $this->systemRules(),
            'tab' => $this->tab($workspaceKey),
        ]);

        return sprintf(
            '%s:%s:%s',
            $baseVersion,
            $workspaceKey,
            Str::lower(substr(sha1($signature ?: $workspaceKey), 0, 8)),
        );
    }

    /**
     * @return list<string>
     */
    public function supportedWorkspaceKeys(): array
    {
        return array_keys(config('pls_assistant.tabs', []));
    }

    public function workspaceLabel(string $workspaceKey): string
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);

        return $this->dbPlaybook($workspaceKey)?->label ?: Str::headline($workspaceKey);
    }

    public function resolveWorkspaceKey(string $workspaceKey): string
    {
        return Arr::has(config('pls_assistant.tabs', []), $workspaceKey)
            ? $workspaceKey
            : 'workflow';
    }

    private function dbPlaybook(string $workspaceKey): ?AssistantTabPlaybook
    {
        if (! array_key_exists($workspaceKey, $this->dbPlaybooks)) {
            $this->dbPlaybooks[$workspaceKey] = AssistantTabPlaybook::query()
                ->with('activeVersion')
                ->where('tab_key', $workspaceKey)
                ->first();
        }

        return $this->dbPlaybooks[$workspaceKey];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     suggested_prompts: list<string>
     * }
     */
    private function normalizeConfigTab(string $workspaceKey): array
    {
        /** @var array<string, mixed> $tab */
        $tab = config("pls_assistant.tabs.{$workspaceKey}", []);

        return [
            'role' => (string) Arr::get($tab, 'role', ''),
            'intro' => (string) Arr::get($tab, 'intro', ''),
            'objectives' => $this->stringList(Arr::get($tab, 'objectives', [])),
            'allowed_capabilities' => $this->stringList(Arr::get($tab, 'allowed_capabilities', [])),
            'disallowed_capabilities' => $this->stringList(Arr::get($tab, 'disallowed_capabilities', [])),
            'suggested_prompts' => $this->stringList(Arr::get($tab, 'suggested_prompts', [])),
            'rules' => $this->stringList(Arr::get($tab, 'rules', [])),
            'guardrails' => $this->stringList(Arr::get($tab, 'guardrails', [])),
            'response_style' => $this->stringList(Arr::get($tab, 'response_style', [])),
        ];
    }

    /**
     * @return array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     suggested_prompts: list<string>
     * }
     */
    private function normalizeVersion(AssistantTabPlaybookVersion $version): array
    {
        return [
            'role' => $version->role,
            'intro' => $version->intro,
            'objectives' => $this->stringList($version->objectives),
            'allowed_capabilities' => $this->stringList($version->allowed_capabilities),
            'disallowed_capabilities' => $this->stringList($version->disallowed_capabilities),
            'suggested_prompts' => $this->stringList($version->suggested_prompts),
            'rules' => $this->stringList($version->rules),
            'guardrails' => $this->stringList($version->guardrails),
            'response_style' => $this->stringList($version->response_style),
        ];
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        return collect(Arr::wrap($value))
            ->map(fn (mixed $item): string => trim((string) $item))
            ->filter()
            ->values()
            ->all();
    }
}
