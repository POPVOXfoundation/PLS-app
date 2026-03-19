<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Collection;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    public string $legislature_id = '';

    public string $review_group_id = '';

    public string $title = '';

    public string $description = '';

    public string $start_date = '';

    public function render(): View
    {
        $this->authorize('create', PlsReview::class);

        $legislatures = $this->legislatures();
        $selectedLegislature = $this->resolveSelectedLegislature($legislatures);
        $reviewGroups = $this->reviewGroups();
        $availableReviewGroups = $this->availableReviewGroups($reviewGroups, $selectedLegislature);

        return view('livewire.pls.reviews.create', [
            'legislatures' => $legislatures,
            'selectedLegislature' => $selectedLegislature,
            'reviewGroups' => $availableReviewGroups,
            'selectedReviewGroup' => $this->resolveSelectedReviewGroup($availableReviewGroups),
            'workflowSteps' => PlsReviewWorkflow::definitions(),
        ])->layout('layouts.app', [
            'title' => __('Create PLS Review'),
        ]);
    }

    public function save(CreatePlsReview $createPlsReview): void
    {
        $this->authorize('create', PlsReview::class);

        $review = $createPlsReview->create([
            'legislature_id' => $this->legislature_id,
            'review_group_id' => $this->review_group_id,
            'title' => $this->title,
            'description' => $this->description,
            'start_date' => $this->start_date,
            'created_by' => auth()->id(),
        ]);

        session()->flash('status', __('Review created and workflow steps seeded.'));

        $this->redirectRoute('pls.reviews.show', ['review' => $review->id], navigate: true);
    }

    public function updatedLegislatureId(): void
    {
        $selectedLegislature = $this->resolveSelectedLegislature($this->legislatures());
        $selectedReviewGroup = $this->resolveSelectedReviewGroup($this->reviewGroups());

        if ($selectedLegislature === null || $selectedReviewGroup === null) {
            return;
        }

        $availableReviewGroups = $this->availableReviewGroups(
            $this->reviewGroups(),
            $selectedLegislature,
        );

        if (! $availableReviewGroups->contains('id', $selectedReviewGroup->id)) {
            $this->review_group_id = '';
        }
    }

    private function legislatures(): Collection
    {
        return Legislature::query()
            ->with('jurisdiction.country')
            ->orderBy('name')
            ->get();
    }

    private function reviewGroups(): Collection
    {
        return ReviewGroup::query()
            ->with(['legislature.jurisdiction.country', 'jurisdiction.country', 'country'])
            ->orderBy('name')
            ->get();
    }

    private function resolveSelectedLegislature(Collection $legislatures): ?Legislature
    {
        if ($this->legislature_id === '') {
            return null;
        }

        return $legislatures->firstWhere('id', (int) $this->legislature_id);
    }

    private function resolveSelectedReviewGroup(Collection $reviewGroups): ?ReviewGroup
    {
        if ($this->review_group_id === '') {
            return null;
        }

        return $reviewGroups->firstWhere('id', (int) $this->review_group_id);
    }

    private function availableReviewGroups(Collection $reviewGroups, ?Legislature $selectedLegislature): Collection
    {
        if ($selectedLegislature === null) {
            return collect();
        }

        $selectedJurisdictionId = $selectedLegislature->jurisdiction_id;
        $selectedCountryId = $selectedLegislature->jurisdiction?->country_id;

        return $reviewGroups->filter(function (ReviewGroup $reviewGroup) use ($selectedCountryId, $selectedJurisdictionId, $selectedLegislature): bool {
            if ($reviewGroup->legislature_id !== null) {
                return $reviewGroup->legislature_id === $selectedLegislature->id;
            }

            if ($reviewGroup->jurisdiction_id !== null) {
                return $reviewGroup->jurisdiction_id === $selectedJurisdictionId;
            }

            if ($reviewGroup->country_id !== null) {
                return $reviewGroup->country_id === $selectedCountryId;
            }

            return true;
        })->values();
    }
}
