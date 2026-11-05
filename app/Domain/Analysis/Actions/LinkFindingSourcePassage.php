<?php

namespace App\Domain\Analysis\Actions;

use App\Domain\Analysis\FindingCitation;
use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LinkFindingSourcePassage
{
    /** @param array<string, mixed> $input */
    public function link(PlsReview $review, Document $document, array $input): FindingCitation
    {
        $validated = Validator::make($input, [
            'finding_id' => ['required', 'integer'],
            'passage' => ['required', 'string', 'min:15', 'max:4000'],
            'relationship' => ['required', Rule::in(['supports', 'contradicts', 'context'])],
            'note' => ['nullable', 'string', 'max:3000'],
        ])->validate();

        return DB::transaction(function () use ($review, $document, $validated): FindingCitation {
            $document = $review->documents()->lockForUpdate()->findOrFail($document->id);
            $finding = $review->findings()->lockForUpdate()->findOrFail($validated['finding_id']);
            $text = $document->extractedText();
            $passage = Str::squish($validated['passage']);
            if (! str_contains(Str::squish($text), $passage)) {
                throw ValidationException::withMessages(['passage' => __('Copy an exact passage from this source. The quoted text was not found.')]);
            }

            return $finding->citations()->updateOrCreate([
                'document_id' => $document->id,
                'passage_hash' => hash('sha256', $passage),
                'relationship' => $validated['relationship'],
            ], [
                'passage' => $passage,
                'note' => $validated['note'] ?? null,
                'finding_fingerprint' => $finding->supportFingerprint(),
                'source_fingerprint' => hash('sha256', $text),
                'reviewed_by' => auth()->id(),
            ]);
        });
    }
}
