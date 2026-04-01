<?php

namespace App\Support\PlsAssistant;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class PlaybookRepository
{
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

        /** @var array{
         *     allowed_capabilities: list<string>,
         *     guardrails: list<string>,
         *     intro: string,
         *     objectives: list<string>,
         *     response_style: list<string>,
         *     role: string,
         *     rules: list<string>,
         *     suggested_prompts: list<string>
         * } $tab
         */
        $tab = config("pls_assistant.tabs.{$workspaceKey}");

        return $tab;
    }

    public function versionForTab(string $workspaceKey): string
    {
        $workspaceKey = $this->resolveWorkspaceKey($workspaceKey);
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

    public function resolveWorkspaceKey(string $workspaceKey): string
    {
        return Arr::has(config('pls_assistant.tabs', []), $workspaceKey)
            ? $workspaceKey
            : 'workflow';
    }
}
