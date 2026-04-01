<?php

namespace App\Ai\Agents;

use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Promptable;
use Stringable;

class ReviewAssistantAgent implements Agent, Conversational
{
    use Promptable;
    use RemembersConversations;

    /**
     * @param  list<string>  $systemRules
     * @param  array{
     *     role: string,
     *     intro: string,
     *     objectives: list<string>,
     *     suggested_prompts: list<string>,
     *     rules: list<string>,
     *     guardrails: list<string>,
     *     allowed_capabilities: list<string>,
     *     response_style: list<string>
     * }  $playbook
     */
    public function __construct(
        protected array $systemRules,
        protected array $playbook,
        protected string $context,
        protected string $workspaceLabel,
        protected string $playbookVersion,
    ) {}

    public function instructions(): Stringable|string
    {
        return implode(PHP_EOL.PHP_EOL, [
            'You are the PLS Bot assistant.',
            'Active tab: '.$this->workspaceLabel,
            'Playbook version: '.$this->playbookVersion,
            'Tab role: '.$this->playbook['role'],
            'System rules:'.PHP_EOL.$this->bullets($this->systemRules),
            'Tab objectives:'.PHP_EOL.$this->bullets($this->playbook['objectives']),
            'Allowed capabilities:'.PHP_EOL.$this->bullets($this->playbook['allowed_capabilities']),
            'Tab rules:'.PHP_EOL.$this->bullets($this->playbook['rules']),
            'Guardrails:'.PHP_EOL.$this->bullets($this->playbook['guardrails']),
            'Response style:'.PHP_EOL.$this->bullets($this->playbook['response_style']),
            'Behavior requirements:'.PHP_EOL.$this->bullets([
                'Stay within the active tab scope.',
                'If the user asks for something outside this tab, refuse briefly and redirect them to the appropriate tab.',
                'If the available record is insufficient, say "I do not have sufficient information to answer this from the current review record."',
                'When drafting analytical or report text, keep it explicitly provisional unless the record shows a final published outcome.',
                'Do not blur source layers. Distinguish global guidance, jurisdiction guidance, and current review facts in plain language.',
                'Use phrases like "According to the WFD manual...", "In this jurisdiction\'s guidance...", or "In this review, the current record shows..." when those layers are present.',
                'Do not use markdown emphasis or markdown headings unless the user explicitly asks for them.',
            ]),
            'Dynamic inquiry context:'.PHP_EOL.$this->context,
        ]);
    }

    protected function maxConversationMessages(): int
    {
        return 20;
    }

    /**
     * @param  list<string>  $lines
     */
    private function bullets(array $lines): string
    {
        return collect($lines)
            ->map(fn (string $line): string => '- '.$line)
            ->implode(PHP_EOL);
    }
}
