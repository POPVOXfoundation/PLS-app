<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Actions\StoreImplementingAgency;
use App\Domain\Stakeholders\Actions\StoreStakeholder;
use App\Domain\Stakeholders\Actions\UpdateStakeholder;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
use App\Support\PlsAssistant\StakeholderSuggestionNormalizer;
use App\Support\Toast;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class StakeholdersPage extends Workspace
{
    use AuthorizesRequests;

    protected string $workspace = 'stakeholders';

    public bool $showAddStakeholderModal = false;

    public bool $showEditStakeholderModal = false;

    public bool $showAddImplementingAgencyModal = false;

    public string $stakeholderTypeFilter = 'all';

    public string $stakeholderEditingId = '';

    public string $stakeholderName = '';

    public string $stakeholderType = StakeholderType::GovernmentAgency->value;

    public string $stakeholderOrganization = '';

    public string $stakeholderEmail = '';

    public string $stakeholderPhone = '';

    public string $implementingAgencyName = '';

    public string $implementingAgencyType = ImplementingAgencyType::Agency->value;

    public string $activeSuggestionId = '';

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
            'suggestedStakeholders' => $this->suggestedStakeholders($review),
        ], $review);
    }

    public function clearStakeholderFilter(): void
    {
        $this->stakeholderTypeFilter = 'all';
    }

    public function prepareStakeholderCreate(): void
    {
        $this->resetStakeholderForm();
        $this->showAddStakeholderModal = true;
    }

    public function prepareImplementingAgencyCreate(): void
    {
        $this->resetImplementingAgencyForm();
        $this->showAddImplementingAgencyModal = true;
    }

    public function prepareSuggestedStakeholder(string $suggestionId): void
    {
        $suggestion = $this->findSuggestion($suggestionId);

        if ($suggestion === null || $suggestion['kind'] !== 'stakeholder') {
            return;
        }

        $this->resetStakeholderForm();
        $this->activeSuggestionId = $suggestion['id'];
        $this->stakeholderName = $suggestion['name'];
        $this->stakeholderType = StakeholderType::tryFrom($suggestion['category'])?->value ?? StakeholderType::Expert->value;
        $this->stakeholderOrganization = '';
        $this->stakeholderEmail = '';
        $this->stakeholderPhone = '';
        $this->showAddStakeholderModal = true;
    }

    public function prepareSuggestedImplementingAgency(string $suggestionId): void
    {
        $suggestion = $this->findSuggestion($suggestionId);

        if ($suggestion === null || $suggestion['kind'] !== 'implementing_agency') {
            return;
        }

        $this->resetImplementingAgencyForm();
        $this->activeSuggestionId = $suggestion['id'];
        $this->implementingAgencyName = $suggestion['name'];
        $this->implementingAgencyType = ImplementingAgencyType::tryFrom($suggestion['category'])?->value ?? ImplementingAgencyType::Agency->value;
        $this->showAddImplementingAgencyModal = true;
    }

    public function dismissSuggestedStakeholder(string $suggestionId): void
    {
        $this->authorizeReviewMutation();

        if (! $this->updateSuggestionStatus($suggestionId, 'dismissed')) {
            return;
        }

        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::success(
            __('Suggestion dismissed'),
            __('The stakeholder suggestion was dismissed.'),
        ));
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

        $this->showEditStakeholderModal = true;
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
        $this->showEditStakeholderModal = false;

        $this->dispatchWorkspaceToast(Toast::success(
            __('Stakeholder updated'),
            __('Stakeholder updated.'),
        ));
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

        $acceptedSuggestionId = $this->activeSuggestionId;

        $this->resetStakeholderForm();
        $this->showAddStakeholderModal = false;

        if ($acceptedSuggestionId !== '') {
            $this->updateSuggestionStatus($acceptedSuggestionId, 'accepted');
        }

        $this->dispatchWorkspaceToast(Toast::success(
            __('Stakeholder added'),
            __('Stakeholder added to the review.'),
        ));
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

        $acceptedSuggestionId = $this->activeSuggestionId;

        $this->resetImplementingAgencyForm();
        $this->showAddImplementingAgencyModal = false;

        if ($acceptedSuggestionId !== '') {
            $this->updateSuggestionStatus($acceptedSuggestionId, 'accepted');
        }

        $this->dispatchWorkspaceToast(Toast::success(
            __('Implementing agency added'),
            __('Implementing agency added to the review.'),
        ));
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

        $this->dispatchWorkspaceToast(Toast::success(
            __('Stakeholder removed'),
            __('Stakeholder removed from the review.'),
        ));
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
                'documents',
                'stakeholders.submissions',
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

        $this->activeSuggestionId = '';

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

        $this->activeSuggestionId = '';

        $this->implementingAgencyType = ImplementingAgencyType::Agency->value;

        $this->resetValidation([
            'implementingAgencyName',
            'implementingAgencyType',
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function suggestedStakeholders(PlsReview $review): Collection
    {
        $existingStakeholders = $review->stakeholders
            ->map(fn (Stakeholder $stakeholder): string => Str::lower($stakeholder->name))
            ->all();
        $existingAgencies = $review->implementingAgencies
            ->map(fn ($agency): string => Str::lower($agency->name))
            ->all();

        return $this->allStoredSuggestions($review)
            ->filter(fn (array $suggestion): bool => ($suggestion['status'] ?? 'suggested') === 'suggested')
            ->reject(function (array $suggestion) use ($existingStakeholders, $existingAgencies): bool {
                $name = Str::lower((string) ($suggestion['name'] ?? ''));

                return ($suggestion['kind'] ?? '') === 'implementing_agency'
                    ? in_array($name, $existingAgencies, true)
                    : in_array($name, $existingStakeholders, true);
            })
            ->sortBy([
                fn (array $suggestion): int => ($suggestion['kind'] ?? '') === 'implementing_agency' ? 0 : 1,
                fn (array $suggestion): string => (string) ($suggestion['name'] ?? ''),
            ])
            ->values();
    }

    private function findSuggestion(string $suggestionId): ?array
    {
        return $this->allStoredSuggestions($this->loadReview())
            ->firstWhere('id', $suggestionId);
    }

    private function updateSuggestionStatus(string $suggestionId, string $status): bool
    {
        foreach ($this->loadReview()->documents as $document) {
            foreach (['legislation_analysis', 'document_analysis'] as $metadataKey) {
                $path = "{$metadataKey}.stakeholder_suggestions";
                $suggestions = $this->normalizeDocumentSuggestions($document, $metadataKey);

                if ($suggestions->isEmpty()) {
                    continue;
                }

                $updated = false;
                $nextSuggestions = $suggestions
                    ->map(function (array $suggestion) use ($suggestionId, $status, &$updated): array {
                        if ($suggestion['id'] === $suggestionId) {
                            $suggestion['status'] = $status;
                            $updated = true;
                        }

                        return $suggestion;
                    })
                    ->values()
                    ->all();

                if (! $updated) {
                    continue;
                }

                $metadata = $document->metadata ?? [];
                data_set($metadata, $path, $nextSuggestions);
                $document->forceFill(['metadata' => $metadata])->save();

                return true;
            }
        }

        return false;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allStoredSuggestions(PlsReview $review): Collection
    {
        return $review->documents
            ->flatMap(function (Document $document): array {
                return [
                    ...$this->normalizeDocumentSuggestions($document, 'legislation_analysis')->all(),
                    ...$this->normalizeDocumentSuggestions($document, 'document_analysis')->all(),
                ];
            })
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function normalizeDocumentSuggestions(Document $document, string $metadataKey): Collection
    {
        $suggestions = data_get($document->metadata, "{$metadataKey}.stakeholder_suggestions", []);

        return collect(app(StakeholderSuggestionNormalizer::class)->normalize(
            $suggestions,
            $document->id,
            $document->title,
        ));
    }
}
