<?php

namespace App\Domain\Analysis\Actions;

use App\Domain\Analysis\Recommendation;
use App\Domain\Analysis\Validation\StoreRecommendationValidator;
use App\Domain\Reviews\PlsReview;

class StoreRecommendation
{
    public function __construct(private StoreRecommendationValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function store(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);

        Recommendation::query()->create([
            'pls_review_id' => $validated['pls_review_id'],
            'finding_id' => $validated['finding_id'],
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'recommendation_type' => $validated['recommendation_type'],
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh([
            'findings',
            'recommendations',
        ]);
    }
}
