<?php

namespace App\Domain\Reviews\Actions;

use App\Domain\Institutions\Committee;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use App\Domain\Reviews\Validation\CreatePlsReviewValidator;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

class CreatePlsReview
{
    public function __construct(
        private DatabaseManager $database,
        private CreatePlsReviewValidator $validator,
    ) {
    }

    public function create(array|CreatePlsReviewData $input): PlsReview
    {
        $validated = $this->validator->validate(
            $input instanceof CreatePlsReviewData
                ? $input->toArray()
                : $input,
        );

        $data = CreatePlsReviewData::from($validated);

        return $this->database->transaction(function () use ($data): PlsReview {
            $committee = Committee::query()
                ->with('legislature.jurisdiction.country')
                ->findOrFail($data->committeeId);

            $legislature = $committee->legislature;
            $jurisdiction = $legislature->jurisdiction;
            $country = $jurisdiction->country;

            $review = PlsReview::query()->create([
                'committee_id' => $committee->id,
                'legislature_id' => $legislature->id,
                'jurisdiction_id' => $jurisdiction->id,
                'country_id' => $country->id,
                'title' => $data->title,
                'slug' => $this->generateUniqueSlug($committee->id, $data->title),
                'description' => $data->description,
                'status' => PlsReviewStatus::Draft,
                'current_step_number' => 1,
                'start_date' => $data->startDate,
                'completed_at' => null,
            ]);

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

    private function generateUniqueSlug(int $committeeId, string $title): string
    {
        $baseSlug = Str::slug($title);
        $slugBase = $baseSlug !== '' ? $baseSlug : 'pls-review';
        $slug = $slugBase;
        $suffix = 2;

        while (
            PlsReview::query()
                ->where('committee_id', $committeeId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = "{$slugBase}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
