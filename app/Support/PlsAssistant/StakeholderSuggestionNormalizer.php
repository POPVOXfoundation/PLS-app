<?php

namespace App\Support\PlsAssistant;

use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StakeholderSuggestionNormalizer
{
    /**
     * @return list<array{
     *     id: string,
     *     kind: string,
     *     name: string,
     *     category: string,
     *     rationale: string,
     *     source: string,
     *     source_document_id: int|null,
     *     source_title: string,
     *     status: string
     * }>
     */
    public function normalize(mixed $value, ?int $sourceDocumentId = null, string $sourceTitle = ''): array
    {
        return Collection::wrap($value)
            ->map(fn (mixed $item): ?array => $this->normalizeItem($item, $sourceDocumentId, $sourceTitle))
            ->filter()
            ->unique('id')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: string,
     *     kind: string,
     *     name: string,
     *     category: string,
     *     rationale: string,
     *     source: string,
     *     source_document_id: int|null,
     *     source_title: string,
     *     status: string
     * }|null
     */
    private function normalizeItem(mixed $item, ?int $sourceDocumentId, string $sourceTitle): ?array
    {
        $fields = is_array($item)
            ? $item
            : $this->parseSuggestionString((string) $item);

        $name = $this->clean((string) ($fields['name'] ?? ''));

        if ($name === '') {
            return null;
        }

        $kind = $this->normalizeKind((string) ($fields['kind'] ?? 'stakeholder'));
        $category = $this->normalizeCategory($kind, (string) ($fields['category'] ?? ''));

        $sourceDocumentId = isset($fields['source_document_id']) && is_numeric($fields['source_document_id'])
            ? (int) $fields['source_document_id']
            : $sourceDocumentId;

        $sourceTitle = $this->clean((string) ($fields['source_title'] ?? $sourceTitle));

        $normalized = [
            'kind' => $kind,
            'name' => $name,
            'category' => $category,
            'rationale' => $this->clean((string) ($fields['rationale'] ?? '')),
            'source' => $this->clean((string) ($fields['source'] ?? '')),
            'source_document_id' => $sourceDocumentId,
            'source_title' => $sourceTitle,
            'status' => $this->normalizeStatus((string) ($fields['status'] ?? 'suggested')),
        ];

        return [
            'id' => (string) ($fields['id'] ?? $this->idFor($normalized)),
            ...$normalized,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function parseSuggestionString(string $value): array
    {
        $fields = [];

        foreach (explode(';', $value) as $part) {
            [$key, $fieldValue] = array_pad(explode('=', $part, 2), 2, '');

            $key = Str::of($key)->lower()->replaceMatches('/[^a-z0-9_]+/', '_')->trim('_')->toString();

            if ($key !== '') {
                $fields[$key] = trim($fieldValue);
            }
        }

        if (! isset($fields['name']) && trim($value) !== '') {
            $fields['name'] = trim($value);
        }

        return $fields;
    }

    private function normalizeKind(string $value): string
    {
        $value = Str::of($value)->lower()->replace('-', '_')->trim()->toString();

        return $value === 'implementing_agency' ? 'implementing_agency' : 'stakeholder';
    }

    private function normalizeCategory(string $kind, string $value): string
    {
        $value = Str::of($value)->lower()->replace('-', '_')->trim()->toString();

        if ($kind === 'implementing_agency') {
            return ImplementingAgencyType::tryFrom($value)?->value ?? ImplementingAgencyType::Agency->value;
        }

        return StakeholderType::tryFrom($value)?->value ?? StakeholderType::Expert->value;
    }

    private function normalizeStatus(string $value): string
    {
        $value = Str::of($value)->lower()->replace('-', '_')->trim()->toString();

        return in_array($value, ['suggested', 'accepted', 'dismissed'], true) ? $value : 'suggested';
    }

    private function clean(string $value): string
    {
        return Str::of($value)
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(500, '')
            ->toString();
    }

    /**
     * @param  array<string, mixed>  $suggestion
     */
    private function idFor(array $suggestion): string
    {
        return substr(sha1(implode('|', [
            $suggestion['kind'] ?? '',
            Str::lower((string) ($suggestion['name'] ?? '')),
            $suggestion['category'] ?? '',
            $suggestion['source_document_id'] ?? '',
            Str::lower((string) ($suggestion['source_title'] ?? '')),
        ])), 0, 16);
    }
}
