<?php

namespace App\Domain\Reviews\Data;

use Carbon\CarbonImmutable;

final readonly class CreatePlsReviewData
{
    public function __construct(
        public int $legislatureId,
        public ?int $reviewGroupId,
        public string $title,
        public ?string $description = null,
        public ?CarbonImmutable $startDate = null,
        public ?int $createdBy = null,
    ) {}

    /**
     * @param  array{
     *     legislature_id: int|string,
     *     review_group_id?: int|string|null,
     *     title: string,
     *     description?: string|null,
     *     start_date?: \DateTimeInterface|string|null,
     *     created_by?: int|string|null
     * }  $input
     */
    public static function from(array $input): self
    {
        return new self(
            legislatureId: (int) $input['legislature_id'],
            reviewGroupId: self::normalizeNullableInt($input['review_group_id'] ?? null),
            title: trim($input['title']),
            description: self::normalizeNullableString($input['description'] ?? null),
            startDate: self::normalizeStartDate($input['start_date'] ?? null),
            createdBy: self::normalizeNullableInt($input['created_by'] ?? null),
        );
    }

    /**
     * @return array{
     *     legislature_id: int,
     *     review_group_id: int|null,
     *     title: string,
     *     description: string|null,
     *     start_date: string|null,
     *     created_by: int|null
     * }
     */
    public function toArray(): array
    {
        return [
            'legislature_id' => $this->legislatureId,
            'review_group_id' => $this->reviewGroupId,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->startDate?->toDateString(),
            'created_by' => $this->createdBy,
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

    private static function normalizeNullableInt(int|string|null $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value)) {
            return $value;
        }

        $trimmedValue = trim($value);

        return $trimmedValue === '' ? null : (int) $trimmedValue;
    }
}
