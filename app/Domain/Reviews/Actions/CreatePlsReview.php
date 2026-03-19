<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use App\Domain\Reviews\Validation\CreatePlsReviewValidator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CreatePlsReview
{
    public function __construct(
        private DatabaseManager $database,
        private CreatePlsReviewValidator $validator,
    ) {}

    public function create(array|CreatePlsReviewData $input): PlsReview
    {
        $validated = $this->validator->validate(
            $input instanceof CreatePlsReviewData
                ? $input->toArray()
                : $input,
        );

        $data = CreatePlsReviewData::from($validated);

        return $this->database->transaction(function () use ($data): PlsReview {
            $legislature = Legislature::query()
                ->with('jurisdiction.country')
                ->findOrFail($data->legislatureId);

            $jurisdiction = $legislature->jurisdiction;
            $country = $jurisdiction->country;
            $reviewGroup = $data->reviewGroupId === null
                ? null
                : ReviewGroup::query()
                    ->with(['legislature', 'jurisdiction', 'country'])
                    ->findOrFail($data->reviewGroupId);

            if ($reviewGroup !== null) {
                $this->ensureReviewGroupMatchesLegislature($reviewGroup, $legislature);
            }

            $review = PlsReview::query()->create([
                'review_group_id' => $reviewGroup?->id,
                'legislature_id' => $legislature->id,
                'jurisdiction_id' => $jurisdiction->id,
                'country_id' => $country->id,
                'created_by' => $data->createdBy,
                'title' => $data->title,
                'slug' => $this->generateUniqueSlug($data->createdBy, $data->title),
                'description' => $data->description,
                'status' => PlsReviewStatus::Draft,
                'current_step_number' => 1,
                'start_date' => $data->startDate,
                'completed_at' => null,
            ]);

            if ($data->createdBy !== null) {
                $review->memberships()->create([
                    'user_id' => $data->createdBy,
                    'role' => PlsReviewMembershipRole::Owner,
                    'invited_by' => null,
                ]);
            }

            $review->steps()->createMany(
                array_map(
                    static fn (array $definition): array => [
                        'step_number' => $definition['number'],
                        'step_key' => $definition['key'],
                        'status' => PlsStepStatus::Pending,
                        'started_at' => null,
                        'completed_at' => null,
                        'notes' => null,
                    ],
                    PlsReviewWorkflow::definitions(),
                ),
            );

            return $review->load('steps');
        });
    }

    private function ensureReviewGroupMatchesLegislature(ReviewGroup $reviewGroup, Legislature $legislature): void
    {
        $jurisdiction = $legislature->jurisdiction;
        $country = $jurisdiction->country;

        if ($reviewGroup->legislature_id !== null && $reviewGroup->legislature_id !== $legislature->id) {
            throw ValidationException::withMessages([
                'review_group_id' => 'Select a review group that belongs to the chosen legislature.',
            ]);
        }

        if ($reviewGroup->jurisdiction_id !== null && $reviewGroup->jurisdiction_id !== $jurisdiction->id) {
            throw ValidationException::withMessages([
                'review_group_id' => 'Select a review group that matches the chosen jurisdiction.',
            ]);
        }

        if ($reviewGroup->country_id !== null && $reviewGroup->country_id !== $country->id) {
            throw ValidationException::withMessages([
                'review_group_id' => 'Select a review group that matches the chosen country.',
            ]);
        }
    }

    private function generateUniqueSlug(?int $createdBy, string $title): string
    {
        $baseSlug = Str::slug($title);
        $slugBase = $baseSlug !== '' ? $baseSlug : 'pls-review';
        $slug = $slugBase;
        $suffix = 2;

        while ($this->slugExistsForOwner($createdBy, $slug)) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    private function slugExistsForOwner(?int $createdBy, string $slug): bool
    {
        return PlsReview::query()
            ->when(
                $createdBy === null,
                static fn ($query) => $query->whereNull('created_by'),
                static fn ($query) => $query->where('created_by', $createdBy),
            )
            ->where('slug', $slug)
            ->exists();
    }
}
