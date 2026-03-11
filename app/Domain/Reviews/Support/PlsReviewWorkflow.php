<?php

namespace App\Domain\Reviews\Support;

use InvalidArgumentException;

class PlsReviewWorkflow
{
    /**
     * @var list<array{number: int, key: string, title: string}>
     */
    private const DEFINITIONS = [
        [
            'number' => 1,
            'key' => 'define_scope',
            'title' => 'Define the objectives and scope of PLS',
        ],
        [
            'number' => 2,
            'key' => 'background_data_plan',
            'title' => 'Collect background information and prepare a data collection plan',
        ],
        [
            'number' => 3,
            'key' => 'stakeholder_plan',
            'title' => 'Identify key stakeholders and prepare a consultation plan',
        ],
        [
            'number' => 4,
            'key' => 'implementation_review',
            'title' => 'Review implementing agencies and delegated legislation',
        ],
        [
            'number' => 5,
            'key' => 'consultations',
            'title' => 'Conduct consultation and public engagement activities',
        ],
        [
            'number' => 6,
            'key' => 'analysis',
            'title' => 'Analyse post-legislative scrutiny findings',
        ],
        [
            'number' => 7,
            'key' => 'draft_report',
            'title' => 'Draft the PLS report',
        ],
        [
            'number' => 8,
            'key' => 'dissemination',
            'title' => 'Disseminate the report and make it publicly accessible',
        ],
        [
            'number' => 9,
            'key' => 'government_response',
            'title' => 'Invite a response from the government to "comply or explain"',
        ],
        [
            'number' => 10,
            'key' => 'follow_up',
            'title' => 'Conduct follow-up to the post-legislative scrutiny activities',
        ],
        [
            'number' => 11,
            'key' => 'evaluation',
            'title' => 'Evaluate the post-legislative scrutiny inquiry results and process',
        ],
    ];

    /**
     * @return list<array{number: int, key: string, title: string}>
     */
    public static function definitions(): array
    {
        return self::DEFINITIONS;
    }

    public static function titleFor(string $stepKey): string
    {
        foreach (self::DEFINITIONS as $definition) {
            if ($definition['key'] === $stepKey) {
                return $definition['title'];
            }
        }

        throw new InvalidArgumentException("Unknown PLS review step key [{$stepKey}].");
    }

    public static function lastStepNumber(): int
    {
        return self::DEFINITIONS[array_key_last(self::DEFINITIONS)]['number'];
    }

    public static function nextStepNumber(int $stepNumber): ?int
    {
        foreach (self::DEFINITIONS as $index => $definition) {
            if ($definition['number'] !== $stepNumber) {
                continue;
            }

            return self::DEFINITIONS[$index + 1]['number'] ?? null;
        }

        throw new InvalidArgumentException("Unknown PLS review step number [{$stepNumber}].");
    }
}
