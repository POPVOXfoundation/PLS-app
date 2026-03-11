<?php

namespace App\Domain\Analysis\Actions;

use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Validation\UpdateFindingValidator;
use App\Domain\Reviews\PlsReview;

class UpdateFinding
{
    public function __construct(private UpdateFindingValidator $validator)
    {
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function update(array $input): PlsReview
    {
        $validated = $this->validator->validate($input);
        $finding = Finding::query()->findOrFail($validated['finding_id']);

        $finding->update([
            'title' => $validated['title'],
            'finding_type' => $validated['finding_type'],
            'summary' => $validated['summary'] ?? null,
            'detail' => $validated['detail'] ?? null,
        ]);

        return PlsReview::query()->findOrFail($validated['pls_review_id'])->fresh([
            'findings',
            'recommendations',
        ]);
    }
}
