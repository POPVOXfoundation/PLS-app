<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\PersistLegislationSourceState;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Actions\StoreImplementingAgency;
use App\Domain\Stakeholders\Actions\StoreStakeholder;
use App\Domain\Stakeholders\Actions\UpdateImplementingAgency;
use App\Domain\Stakeholders\Actions\UpdateStakeholder;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use App\Jobs\ProcessReviewLegislationSource;
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

    public bool $showEditImplementingAgencyModal = false;

    public string $stakeholderTypeFilter = 'all';

    public string $stakeholderEditingId = '';

    public string $implementingAgencyEditingId = '';

    public string $stakeholderName = '';

    public string $stakeholderType = 'Government agency';

    public string $stakeholderOrganization = '';

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
        $legislationSources = $this->legislationSources($review);

        return $this->renderWorkspaceView('livewire.pls.reviews.stakeholders-page', [
            'review' => $review,
            'stakeholderTypes' => $this->stakeholderTypeOptions($review),
            'filteredStakeholders' => $this->filteredStakeholders($review),
            'implementingAgencyTypes' => ImplementingAgencyType::cases(),
            'hasLegislationSources' => $legislationSources->isNotEmpty(),
            'hasProcessingLegislationSuggestions' => $legislationSources->contains(
                fn (Document $document): bool => $this->legislationSourceIsProcessing($document),
            ),
            'hasGeneratedLegislationAgencySuggestions' => $legislationSources->contains(
                fn (Document $document): bool => $this->legislationAgencySuggestions($document)->isNotEmpty(),
            ),
            'suggestedImplementingAgencies' => $this->suggestedImplementingAgencies($review),
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

    public function generateImplementingAgencySuggestions(): void
    {
        $this->authorizeReviewMutation();

        $sources = $this->legislationSources($this->review)
            ->filter(fn (Document $document): bool => ! $this->legislationSourceIsProcessing($document))
            ->filter(fn (Document $document): bool => $this->legislationAgencySuggestions($document)->isEmpty());

        if ($sources->isEmpty()) {
            return;
        }

        $state = app(PersistLegislationSourceState::class);

        foreach ($sources as $source) {
            $state->resetForRetry($source);
            ProcessReviewLegislationSource::dispatch($source->id);
        }

        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::warning(
            __('Preparing implementing agency suggestions'),
            __('PLSAssist is reading the legislation and identifying the public bodies responsible for implementation.'),
        ));
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
        $this->stakeholderType = $this->stakeholderTypeLabel($suggestion['category']);
        $this->stakeholderOrganization = '';
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
        $this->stakeholderType = $this->stakeholderTypeLabel($stakeholder->stakeholder_type);
        $this->stakeholderOrganization = $stakeholder->contact_details['organization'] ?? '';

        $this->resetValidation([
            'stakeholderEditingId',
            'stakeholderName',
            'stakeholderType',
            'stakeholderOrganization',
        ]);

        $this->showEditStakeholderModal = true;
    }

    public function startEditingImplementingAgency(int $agencyId): void
    {
        $this->authorizeReviewMutation();

        $agency = $this->review->implementingAgencies()
            ->whereKey($agencyId)
            ->first();

        if ($agency === null) {
            return;
        }

        $this->implementingAgencyEditingId = (string) $agency->id;
        $this->implementingAgencyName = $agency->name;
        $this->implementingAgencyType = $agency->agency_type->value;

        $this->resetValidation([
            'implementingAgencyEditingId',
            'implementingAgencyName',
            'implementingAgencyType',
        ]);

        $this->showEditImplementingAgencyModal = true;
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
                    'source' => $stakeholder->contact_details['source'] ?? null,
                ],
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'stakeholder_id' => 'stakeholderEditingId',
                'name' => 'stakeholderName',
                'stakeholder_type' => 'stakeholderType',
                'contact_details.organization' => 'stakeholderOrganization',
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

        $suggestion = $this->activeSuggestionId === '' ? null : $this->findSuggestion($this->activeSuggestionId);

        try {
            $this->review = $action->store([
                'pls_review_id' => $this->review->id,
                'name' => $this->stakeholderName,
                'stakeholder_type' => $this->stakeholderType,
                'contact_details' => [
                    'organization' => $this->blankToNull($this->stakeholderOrganization),
                    'source' => $suggestion === null
                        ? __('Added manually')
                        : ($suggestion['source_title'] !== '' ? $suggestion['source_title'] : __('From source text')),
                ],
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'name' => 'stakeholderName',
                'stakeholder_type' => 'stakeholderType',
                'contact_details.organization' => 'stakeholderOrganization',
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

    public function updateImplementingAgency(UpdateImplementingAgency $action): void
    {
        $this->authorizeReviewMutation();

        try {
            $this->review = $action->update([
                'agency_id' => $this->implementingAgencyEditingId,
                'pls_review_id' => $this->review->id,
                'name' => $this->implementingAgencyName,
                'agency_type' => $this->implementingAgencyType,
            ])->fresh();
        } catch (ValidationException $exception) {
            $this->mapValidationErrors($exception, [
                'agency_id' => 'implementingAgencyEditingId',
                'name' => 'implementingAgencyName',
                'agency_type' => 'implementingAgencyType',
            ]);

            return;
        }

        $this->resetImplementingAgencyForm();
        $this->showEditImplementingAgencyModal = false;

        $this->dispatchWorkspaceToast(Toast::success(
            __('Implementing agency updated'),
            __('Implementing agency updated.'),
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

    public function removeImplementingAgency(int $agencyId): void
    {
        $this->authorizeReviewMutation();

        $agency = $this->review->implementingAgencies()->whereKey($agencyId)->first();

        if ($agency === null) {
            return;
        }

        $agency->delete();
        $this->review = $this->loadReview();

        $this->dispatchWorkspaceToast(Toast::success(
            __('Implementing agency removed'),
            __('Implementing agency removed from the review.'),
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
            fn (Stakeholder $stakeholder): bool => $this->stakeholderTypeKey($stakeholder->stakeholder_type) === $this->stakeholderTypeKey($this->stakeholderTypeFilter),
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
        ]);

        $this->activeSuggestionId = '';

        $this->stakeholderType = 'Government agency';

        $this->resetValidation([
            'stakeholderEditingId',
            'stakeholderName',
            'stakeholderType',
            'stakeholderOrganization',
        ]);
    }

    public function stakeholderTypeLabel(string $type): string
    {
        $type = trim($type);

        if ($type === '') {
            return __('Unspecified');
        }

        return match (Str::lower($type)) {
            StakeholderType::Ngo->value => 'NGO',
            default => Str::headline($type),
        };
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{value: string, label: string}>
     */
    private function stakeholderTypeOptions(PlsReview $review): Collection
    {
        return collect([
            ...array_map(static fn (StakeholderType $type): string => $type->value, StakeholderType::cases()),
            ...$review->stakeholders->map(fn (Stakeholder $stakeholder): string => $stakeholder->stakeholder_type)->all(),
        ])
            ->map(fn (string $type): string => trim($type))
            ->filter()
            ->unique(fn (string $type): string => $this->stakeholderTypeKey($type))
            ->map(fn (string $type): array => [
                'value' => $this->stakeholderTypeLabel($type),
                'label' => $this->stakeholderTypeLabel($type),
            ])
            ->sortBy('label')
            ->values();
    }

    private function stakeholderTypeKey(string $type): string
    {
        return Str::of($type)
            ->lower()
            ->replace(['_', '-'], ' ')
            ->squish()
            ->toString();
    }

    private function resetImplementingAgencyForm(): void
    {
        $this->reset([
            'implementingAgencyEditingId',
            'implementingAgencyName',
        ]);

        $this->activeSuggestionId = '';

        $this->implementingAgencyType = ImplementingAgencyType::Agency->value;

        $this->resetValidation([
            'implementingAgencyEditingId',
            'implementingAgencyName',
            'implementingAgencyType',
        ]);
    }

    /**
     * @return EloquentCollection<int, Document>
     */
    private function legislationSources(PlsReview $review): EloquentCollection
    {
        return $review->documents
            ->filter(fn (Document $document): bool => $document->document_type === DocumentType::LegislationText)
            ->values();
    }

    private function legislationSourceIsProcessing(Document $document): bool
    {
        return data_get($document->metadata, 'legislation_analysis.status') === 'processing'
            || in_array(data_get($document->metadata, 'extraction.status'), ['queued', 'processing'], true);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function legislationAgencySuggestions(Document $document): Collection
    {
        return $this->normalizeDocumentSuggestions($document, 'legislation_analysis')
            ->where('kind', 'implementing_agency')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function suggestedImplementingAgencies(PlsReview $review): Collection
    {
        $existingAgencies = $review->implementingAgencies
            ->map(fn (ImplementingAgency $agency): string => Str::lower($agency->name))
            ->all();

        return $this->legislationSources($review)
            ->flatMap(fn (Document $document): array => $this->legislationAgencySuggestions($document)->all())
            ->filter(fn (array $suggestion): bool => $suggestion['status'] === 'suggested')
            ->reject(fn (array $suggestion): bool => in_array(Str::lower($suggestion['name']), $existingAgencies, true))
            ->unique('id')
            ->sortBy('name')
            ->values();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function suggestedStakeholders(PlsReview $review): Collection
    {
        $existingStakeholders = $review->stakeholders
            ->map(fn (Stakeholder $stakeholder): string => Str::lower($stakeholder->name))
            ->all();

        return $this->allStoredSuggestions($review)
            ->filter(fn (array $suggestion): bool => ($suggestion['status'] ?? 'suggested') === 'suggested')
            ->where('kind', 'stakeholder')
            ->reject(function (array $suggestion) use ($existingStakeholders): bool {
                $name = Str::lower((string) ($suggestion['name'] ?? ''));

                return in_array($name, $existingStakeholders, true);
            })
            ->sortBy(fn (array $suggestion): string => (string) ($suggestion['name'] ?? ''))
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
