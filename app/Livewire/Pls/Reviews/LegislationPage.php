<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Legislation\Actions\AttachLegislationToReview;
use App\Domain\Legislation\Actions\CreateLegislationForReview;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class LegislationPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'legislation';

    public string $attachLegislationId = '';

    public string $attachLegislationRelationshipType = ReviewLegislationRelationshipType::Related->value;

    public string $newLegislationTitle = '';

    public string $newLegislationShortTitle = '';

    public string $newLegislationType = LegislationType::Act->value;

    public string $newLegislationDateEnacted = '';

    public string $newLegislationSummary = '';

    public string $newLegislationRelationshipType = ReviewLegislationRelationshipType::Primary->value;

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.legislation-page', [
            'review' => $review,
            'attachableLegislation' => $this->attachableLegislation($review),
            'legislationTypes' => LegislationType::cases(),
            'legislationRelationshipTypes' => ReviewLegislationRelationshipType::cases(),
        ], $review);
    }

    public function attachLegislation(AttachLegislationToReview $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->attach([
                'pls_review_id' => $this->review->id,
                'legislation_id' => $this->attachLegislationId,
                'relationship_type' => $this->attachLegislationRelationshipType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'legislation_id' => 'attachLegislationId',
                'relationship_type' => 'attachLegislationRelationshipType',
            ]);

            return;
        }

        $this->reset('attachLegislationId');
        $this->resetValidation(['attachLegislationId']);

        $this->dispatch('review-workspace-updated', status: __('Legislation linked to the review.'));
    }

    public function createLegislation(CreateLegislationForReview $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->create([
                'pls_review_id' => $this->review->id,
                'title' => $this->newLegislationTitle,
                'short_title' => $this->blankToNull($this->newLegislationShortTitle),
                'legislation_type' => $this->newLegislationType,
                'date_enacted' => $this->blankToNull($this->newLegislationDateEnacted),
                'summary' => $this->blankToNull($this->newLegislationSummary),
                'relationship_type' => $this->newLegislationRelationshipType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'newLegislationTitle',
                'short_title' => 'newLegislationShortTitle',
                'legislation_type' => 'newLegislationType',
                'date_enacted' => 'newLegislationDateEnacted',
                'summary' => 'newLegislationSummary',
                'relationship_type' => 'newLegislationRelationshipType',
            ]);

            return;
        }

        $this->resetLegislationForm();

        $this->dispatch('review-workspace-updated', status: __('Legislation created and linked to the review.'));
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function mapValidationErrors(ValidationException $exception, array $mapping): void
    {
        $this->resetValidation(array_values($mapping));

        foreach ($exception->errors() as $key => $messages) {
            $mappedKey = $mapping[$key] ?? $key;

            foreach ($messages as $message) {
                $this->addError($mappedKey, $message);
            }
        }
    }

    private function authorizeReviewMutation(): void
    {
        $this->authorize('update', $this->review);
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'legislation',
            ])
            ->findOrFail($this->review->getKey());
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Legislation>
     */
    private function attachableLegislation(PlsReview $review): \Illuminate\Database\Eloquent\Collection
    {
        return Legislation::query()
            ->where('jurisdiction_id', $review->jurisdiction_id)
            ->whereNotIn('id', $review->legislation->modelKeys())
            ->orderBy('title')
            ->get();
    }

    private function resetLegislationForm(): void
    {
        $this->reset([
            'attachLegislationId',
            'newLegislationTitle',
            'newLegislationShortTitle',
            'newLegislationDateEnacted',
            'newLegislationSummary',
        ]);

        $this->attachLegislationRelationshipType = ReviewLegislationRelationshipType::Related->value;
        $this->newLegislationType = LegislationType::Act->value;
        $this->newLegislationRelationshipType = ReviewLegislationRelationshipType::Primary->value;

        $this->resetValidation([
            'attachLegislationId',
            'newLegislationTitle',
            'newLegislationShortTitle',
            'newLegislationType',
            'newLegislationDateEnacted',
            'newLegislationSummary',
            'newLegislationRelationshipType',
        ]);
    }
}
