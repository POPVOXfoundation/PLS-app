<?php

namespace App\Domain\Reviews\Data;

use Carbon\CarbonImmutable;

final readonly class CreatePlsReviewData
{
    public function __construct(
        public int $committeeId,
        public string $title,
        public ?string $description = null,
        public ?CarbonImmutable $startDate = null,
    ) {
    }

    /**
     * @param  array{
     *     committee_id: int|string,
     *     title: string,
     *     description?: string|null,
     *     start_date?: \DateTimeInterface|string|null
     * }  $input
     */
    public static function from(array $input): self
    {
        return new self(
            committeeId: (int) $input['committee_id'],
            title: trim($input['title']),
            description: self::normalizeNullableString($input['description'] ?? null),
            startDate: self::normalizeStartDate($input['start_date'] ?? null),
        );
    }

    /**
     * @return array{
     *     committee_id: int,
     *     title: string,
     *     description: string|null,
     *     start_date: string|null
     * }
     */
    public function toArray(): array
    {
        return [
            'committee_id' => $this->committeeId,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->startDate?->toDateString(),
        ];
    }

    private static function normalizeNullableString(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : $trimmedValue;
    }

    private static function normalizeStartDate(\DateTimeInterface|string|null $value): ?CarbonImmutable
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }

        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : CarbonImmutable::parse($trimmedValue);
    }
}
