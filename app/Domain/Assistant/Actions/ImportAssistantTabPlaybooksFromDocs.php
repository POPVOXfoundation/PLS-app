<?php

namespace App\Domain\Assistant\Actions;

use App\Domain\Assistant\AssistantTabPlaybook;
use App\Domain\Assistant\AssistantTabPlaybookSeedData;
use Illuminate\Support\Arr;

class ImportAssistantTabPlaybooksFromDocs
{
    public function handle(?int $createdBy = null): void
    {
        collect($this->definitions())
            ->each(function (array $definition, string $tabKey) use ($createdBy): void {
                $record = AssistantTabPlaybook::query()->firstOrCreate(
                    ['tab_key' => $tabKey],
                    ['label' => $definition['label']],
                );

                if ($record->versions()->doesntExist()) {
                    $version = $record->versions()->create([
                        ...Arr::except($definition, ['label', 'source_file']),
                        'version_number' => 1,
                        'change_note' => sprintf(
                            'Seeded from %s',
                            $definition['source_file'],
                        ),
                        'created_by' => $createdBy,
                    ]);

                    $record->forceFill([
                        'active_version_id' => $version->id,
                        'label' => $definition['label'],
                    ])->save();

                    return;
                }

                if ($record->active_version_id === null) {
                    $record->forceFill([
                        'active_version_id' => $record->versions()->value('id'),
                    ])->save();
                }
            });
    }

    /**
     * @return array<string, array{
     *     allowed_capabilities: list<string>,
     *     disallowed_capabilities: list<string>,
     *     guardrails: list<string>,
     *     intro: string,
     *     label: string,
     *     objectives: list<string>,
     *     response_style: list<string>,
     *     role: string,
     *     rules: list<string>,
     *     source_file: string,
     *     suggested_prompts: list<string>
     * }>
     */
    private function definitions(): array
    {
        return AssistantTabPlaybookSeedData::definitions();
    }
}
