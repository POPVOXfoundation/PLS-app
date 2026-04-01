<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Actions\StoreImplementingAgency;
use App\Domain\Stakeholders\Actions\StoreStakeholder;
use App\Domain\Stakeholders\Actions\UpdateStakeholder;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Validation\ValidationException;

class StakeholdersPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'stakeholders';

    public string $stakeholderTypeFilter = 'all';

    public string $stakeholderEditingId = '';

    public string $stakeholderName = '';

    public string $stakeholderType = StakeholderType::GovernmentAgency->value;

    public string $stakeholderOrganization = '';

    public string $stakeholderEmail = '';

    public string $stakeholderPhone = '';

    public string $implementingAgencyName = '';

    public string $implementingAgencyType = ImplementingAgencyType::Agency->value;

    public function mount(PlsReview $review): void
    {
        parent::mount($review);
    }

    public function render(): View
    {
        $review = $this->loadReview();

        return $this->renderWorkspaceView('livewire.pls.reviews.stakeholders-page', [
            'review' => $review,
            'stakeholderTypes' => StakeholderType::cases(),
            'filteredStakeholders' => $this->filteredStakeholders($review),
            'implementingAgencyTypes' => ImplementingAgencyType::cases(),
        ], $review);
    }

    public function clearStakeholderFilter(): void
    {
        $this->stakeholderTypeFilter = 'all';
    }

    public function prepareStakeholderCreate(): void
    {
        $this->resetStakeholderForm();
    }

    public function prepareImplementingAgencyCreate(): void
    {
        $this->resetImplementingAgencyForm();
    }

    public function prepareSubmissionCreate(?int $stakeholderId = null): void
    {
        if (
            $stakeholderId !== null
            && ! $this->review->stakeholders()->whereKey($stakeholderId)->exists()
        ) {
            return;
        }

        $this->redirectRoute('pls.reviews.consultations', [
            'review' => $this->review,
            'stakeholder' => $stakeholderId,
        ], navigate: true);
    }

    public function startEditingStakeholder(int $stakeholderId): void
    {
        $this->authorizeReviewMutation();

        $stakeholder = $this->review->stakeholders()
            ->whereKey($stakeholderId)
            ->first();

        if ($stakeholder === null) {
            return;
        }

        $this->stakeholderEditingId = (string) $stakeholder->id;
        $this->stakeholderName = $stakeholder->name;
        $this->stakeholderType = $stakeholder->stakeholder_type->value;
        $this->stakeholderOrganization = $stakeholder->contact_details['organization'] ?? '';
        $this->stakeholderEmail = $stakeholder->contact_details['email'] ?? '';
        $this->stakeholderPhone = $stakeholder->contact_details['phone'] ?? '';

        $this->resetValidation([
            'stakeholderEditingId',
            'stakeholderName',
            'stakeholderType',
            'stakeholderOrganization',
            'stakeholderEmail',
            'stakeholderPhone',
        ]);
    }

    public function updateStakeholder(UpdateStakeholder $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'stakeholder_id' => $this->stakeholderEditingId,
                'pls_review_id' => $this->review->id,
                'name' => $this->stakeholderName,
                'stakeholder_type' => $this->stakeholderType,
                'contact_details' => [
                    'organization' => $this->blankToNull($this->stakeholderOrganization),
                    'email' => $this->blankToNull($this->stakeholderEmail),
                    'phone' => $this->blankToNull($this->stakeholderPhone),
                ],
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'stakeholder_id' => 'stakeholderEditingId',
                'name' => 'stakeholderName',
                'stakeholder_type' => 'stakeholderType',
                'contact_details.organization' => 'stakeholderOrganization',
                'contact_details.email' => 'stakeholderEmail',
                'contact_details.phone' => 'stakeholderPhone',
            ]);

            return;
        }

        $this->resetStakeholderForm();

        $this->dispatch('review-workspace-updated', status: __('Stakeholder updated.'));
    }

    public function storeStakeholder(StoreStakeholder $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'name' => $this->stakeholderName,
                'stakeholder_type' => $this->stakeholderType,
                'contact_details' => [
                    'organization' => $this->blankToNull($this->stakeholderOrganization),
                    'email' => $this->blankToNull($this->stakeholderEmail),
                    'phone' => $this->blankToNull($this->stakeholderPhone),
                ],
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'name' => 'stakeholderName',
                'stakeholder_type' => 'stakeholderType',
                'contact_details.organization' => 'stakeholderOrganization',
                'contact_details.email' => 'stakeholderEmail',
                'contact_details.phone' => 'stakeholderPhone',
            ]);

            return;
        }

        $this->resetStakeholderForm();

        $this->dispatch('review-workspace-updated', status: __('Stakeholder added to the review.'));
    }

    public function storeImplementingAgency(StoreImplementingAgency $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'name' => $this->implementingAgencyName,
                'agency_type' => $this->implementingAgencyType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'name' => 'implementingAgencyName',
                'agency_type' => 'implementingAgencyType',
            ]);

            return;
        }

        $this->resetImplementingAgencyForm();

        $this->dispatch('review-workspace-updated', status: __('Implementing agency added to the review.'));
    }

    public function removeStakeholder(int $stakeholderId): void
    {
        $this->authorizeReviewMutation();

        $stakeholder = $this->review->stakeholders()->whereKey($stakeholderId)->first();

        if ($stakeholder === null) {
            return;
        }

        $stakeholder->delete();
        $this->review = $this->loadReview();

        $this->dispatch('review-workspace-updated', status: __('Stakeholder removed from the review.'));
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
                'stakeholders',
                'implementingAgencies',
            ])
            ->findOrFail($this->review->getKey());
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function filteredStakeholders(PlsReview $review): EloquentCollection
    {
        if ($this->stakeholderTypeFilter === 'all') {
            return $review->stakeholders;
        }

        return $review->stakeholders->filter(
            fn (Stakeholder $stakeholder): bool => $stakeholder->stakeholder_type->value === $this->stakeholderTypeFilter,
        )->values();
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function stakeholdersWithSubmissions(PlsReview $review): EloquentCollection
    {
        return $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => $stakeholder->submissions->isNotEmpty())
            ->values();
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function stakeholdersAwaitingEvidence(PlsReview $review): EloquentCollection
    {
        return $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => $stakeholder->submissions->isEmpty())
            ->values();
    }

    /**
     * @return EloquentCollection<int, Stakeholder>
     */
    private function stakeholdersMissingContacts(PlsReview $review): EloquentCollection
    {
        return $review->stakeholders
            ->filter(fn (Stakeholder $stakeholder): bool => ! $this->hasContactDetails($stakeholder))
            ->values();
    }

    private function hasContactDetails(Stakeholder $stakeholder): bool
    {
        $contactDetails = $stakeholder->contact_details ?? [];

        return filled($contactDetails['organization'] ?? null)
            || filled($contactDetails['email'] ?? null)
            || filled($contactDetails['phone'] ?? null);
    }

    private function blankToNull(string $value): ?string
    {
        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function resetStakeholderForm(): void
    {
        $this->reset([
            'stakeholderEditingId',
            'stakeholderName',
            'stakeholderOrganization',
            'stakeholderEmail',
            'stakeholderPhone',
        ]);

        $this->stakeholderType = StakeholderType::GovernmentAgency->value;

        $this->resetValidation([
            'stakeholderEditingId',
            'stakeholderName',
            'stakeholderType',
            'stakeholderOrganization',
            'stakeholderEmail',
            'stakeholderPhone',
        ]);
    }

    private function resetImplementingAgencyForm(): void
    {
        $this->reset([
            'implementingAgencyName',
        ]);

        $this->implementingAgencyType = ImplementingAgencyType::Agency->value;

        $this->resetValidation([
            'implementingAgencyName',
            'implementingAgencyType',
        ]);
    }
}
