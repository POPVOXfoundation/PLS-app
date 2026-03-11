<?php

namespace App\Domain\Analysis\Actions;

use App\Domain\Analysis\Recommendation;
use App\Domain\Analysis\Validation\UpdateRecommendationValidator;
use App\Domain\Reviews\PlsReview;

class UpdateRecommendation
{
    public function __construct(private UpdateRecommendationValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        $recommendation = Recommendation::query()->findOrFail($validated['recommendation_id']);

        $recommendation->update([
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
