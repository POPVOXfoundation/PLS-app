<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Analysis\Actions\StoreFinding;
use App\Domain\Analysis\Actions\StoreRecommendation;
use App\Domain\Analysis\Actions\UpdateFinding;
use App\Domain\Analysis\Actions\UpdateRecommendation;
use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class AnalysisPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'analysis';

    public bool $showAddFindingModal = false;

    public bool $showEditFindingModal = false;

    public bool $showEditRecommendationModal = false;

    public string $findingTitle = '';

    public string $findingType = FindingType::ImplementationGap->value;

    public string $findingEditingId = '';

    public string $findingSummary = '';

    public string $findingDetail = '';

    public string $recommendationFindingId = '';

    public string $recommendationEditingId = '';

    public string $recommendationTitle = '';

    public string $recommendationDescription = '';

    public string $recommendationType = RecommendationType::ImproveImplementation->value;

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function prepareFindingCreate(): void
    {
        $this->resetFindingForm();
        $this->showAddFindingModal = true;
    }

    public function prepareRecommendationCreate(?int $findingId = null): void
    {
        $this->resetRecommendationForm();

        if (
            $findingId !== null
            && $this->review->findings()->whereKey($findingId)->exists()
        ) {
            $this->recommendationFindingId = (string) $findingId;
        }

        $this->js("window.Flux.modal('add-analysis-recommendation').show()");
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.analysis-page', [
            'review' => $review,
            'findingTypes' => FindingType::cases(),
            'recommendationTypes' => RecommendationType::cases(),
        ], $review);
    }

    public function storeFinding(StoreFinding $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'title' => $this->findingTitle,
                'finding_type' => $this->findingType,
                'summary' => $this->blankToNull($this->findingSummary),
                'detail' => $this->blankToNull($this->findingDetail),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'title' => 'findingTitle',
                'finding_type' => 'findingType',
                'summary' => 'findingSummary',
                'detail' => 'findingDetail',
            ]);

            return;
        }

        $this->resetFindingForm();
        $this->showAddFindingModal = false;

        $this->dispatch('review-workspace-updated', status: __('Finding added to the review.'));
    }

    public function startEditingFinding(int $findingId): void
    {
        $this->authorizeReviewMutation();

        $finding = $this->review->findings()
            ->whereKey($findingId)
            ->first();

        if ($finding === null) {
            return;
        }

        $this->findingEditingId = (string) $finding->id;
        $this->findingTitle = $finding->title;
        $this->findingType = $finding->finding_type->value;
        $this->findingSummary = $finding->summary ?? '';
        $this->findingDetail = $finding->detail ?? '';

        $this->resetValidation([
            'findingEditingId',
            'findingTitle',
            'findingType',
            'findingSummary',
            'findingDetail',
        ]);

        $this->showEditFindingModal = true;
    }

    public function updateFinding(UpdateFinding $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'finding_id' => $this->findingEditingId,
                'pls_review_id' => $this->review->id,
                'title' => $this->findingTitle,
                'finding_type' => $this->findingType,
                'summary' => $this->blankToNull($this->findingSummary),
                'detail' => $this->blankToNull($this->findingDetail),
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'finding_id' => 'findingEditingId',
                'title' => 'findingTitle',
                'finding_type' => 'findingType',
                'summary' => 'findingSummary',
                'detail' => 'findingDetail',
            ]);

            return;
        }

        $this->resetFindingForm();
        $this->showEditFindingModal = false;

        $this->dispatch('review-workspace-updated', status: __('Finding updated.'));
    }

    public function storeRecommendation(StoreRecommendation $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'finding_id' => $this->recommendationFindingId,
                'title' => $this->recommendationTitle,
                'description' => $this->blankToNull($this->recommendationDescription),
                'recommendation_type' => $this->recommendationType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'finding_id' => 'recommendationFindingId',
                'title' => 'recommendationTitle',
                'description' => 'recommendationDescription',
                'recommendation_type' => 'recommendationType',
            ]);

            return;
        }

        $this->resetRecommendationForm();
        $this->js("window.Flux.modal('add-analysis-recommendation').close()");

        $this->dispatch('review-workspace-updated', status: __('Recommendation added to the review.'));
    }

    public function startEditingRecommendation(int $recommendationId): void
    {
        $this->authorizeReviewMutation();

        $recommendation = $this->review->recommendations()
            ->whereKey($recommendationId)
            ->first();

        if ($recommendation === null) {
            return;
        }

        $this->recommendationEditingId = (string) $recommendation->id;
        $this->recommendationFindingId = (string) $recommendation->finding_id;
        $this->recommendationTitle = $recommendation->title;
        $this->recommendationDescription = $recommendation->description ?? '';
        $this->recommendationType = $recommendation->recommendation_type->value;

        $this->resetValidation([
            'recommendationEditingId',
            'recommendationFindingId',
            'recommendationTitle',
            'recommendationDescription',
            'recommendationType',
        ]);

        $this->showEditRecommendationModal = true;
    }

    public function updateRecommendation(UpdateRecommendation $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'recommendation_id' => $this->recommendationEditingId,
                'pls_review_id' => $this->review->id,
                'finding_id' => $this->recommendationFindingId,
                'title' => $this->recommendationTitle,
                'description' => $this->blankToNull($this->recommendationDescription),
                'recommendation_type' => $this->recommendationType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'recommendation_id' => 'recommendationEditingId',
                'finding_id' => 'recommendationFindingId',
                'title' => 'recommendationTitle',
                'description' => 'recommendationDescription',
                'recommendation_type' => 'recommendationType',
            ]);

            return;
        }

        $this->resetRecommendationForm();
        $this->showEditRecommendationModal = false;

        $this->dispatch('review-workspace-updated', status: __('Recommendation updated.'));
    }

    public function confirmDeletion(string $type, int $id): void
    {
        $this->authorizeReviewMutation();

        match ($type) {
            'finding' => $this->performFindingDeletion($id),
            'recommendation' => $this->performRecommendationDeletion($id),
            default => null,
        };
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

    private function loadReview(): PlsReview
    {
        return PlsReview::query()
            ->with([
                'findings',
                'recommendations.finding',
            ])
            ->findOrFail($this->review->getKey());
    }

    private function performFindingDeletion(int $findingId): void
    {
        $finding = $this->review->findings()
            ->whereKey($findingId)
            ->first();

        if ($finding === null) {
            return;
        }

        $finding->delete();
        $this->review = $this->loadReview();

        if ($this->findingEditingId === (string) $findingId) {
            $this->resetFindingForm();
            $this->showEditFindingModal = false;
        }

        $this->dispatch('review-workspace-updated', status: __('Finding removed from the review.'));
    }

    private function performRecommendationDeletion(int $recommendationId): void
    {
        $recommendation = $this->review->recommendations()
            ->whereKey($recommendationId)
            ->first();

        if ($recommendation === null) {
            return;
        }

        $recommendation->delete();
        $this->review = $this->loadReview();

        if ($this->recommendationEditingId === (string) $recommendationId) {
            $this->resetRecommendationForm();
            $this->showEditRecommendationModal = false;
        }

        $this->dispatch('review-workspace-updated', status: __('Recommendation removed from the review.'));
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resetFindingForm(): void
    {
        $this->reset([
            'findingEditingId',
            'findingTitle',
            'findingSummary',
            'findingDetail',
        ]);

        $this->findingType = FindingType::ImplementationGap->value;

        $this->resetValidation([
            'findingTitle',
            'findingType',
            'findingSummary',
            'findingDetail',
            'findingEditingId',
        ]);
    }

    private function resetRecommendationForm(): void
    {
        $this->reset([
            'recommendationEditingId',
            'recommendationFindingId',
            'recommendationTitle',
            'recommendationDescription',
        ]);

        $this->recommendationType = RecommendationType::ImproveImplementation->value;

        $this->resetValidation([
            'recommendationFindingId',
            'recommendationTitle',
            'recommendationDescription',
            'recommendationType',
            'recommendationEditingId',
        ]);
    }
}
