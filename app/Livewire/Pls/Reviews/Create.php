<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Institutions\Committee;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use App\Domain\Reviews\Validation\CreatePlsReviewValidator;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Component;

class Create extends Component
{
    use AuthorizesRequests;

    protected CreatePlsReviewValidator $reviewValidator;

    public string $committee_id = '';

    public string $title = '';

    public string $description = '';

    public string $start_date = '';

    public function boot(CreatePlsReviewValidator $reviewValidator): void
    {
        $this->reviewValidator = $reviewValidator;
    }

    public function render(): View
    {
        $this->authorize('create', PlsReview::class);

        $committees = $this->committees();

        return view('livewire.pls.reviews.create', [
            'committees' => $committees,
            'selectedCommittee' => $this->resolveSelectedCommittee($committees),
            'workflowSteps' => PlsReviewWorkflow::definitions(),
        ])->layout('layouts.app', [
            'title' => __('Create PLS Review'),
        ]);
    }

    public function save(CreatePlsReview $createPlsReview): void
    {
        $this->authorize('create', PlsReview::class);

        $validated = $this->validate();

        $review = $createPlsReview->create(CreatePlsReviewData::from($validated));

        session()->flash('status', __('Review created and workflow steps seeded.'));

        $this->redirectRoute('pls.reviews.show', ['review' => $review->id], navigate: true);
    }

    protected function rules(): array
    {
        return $this->reviewValidator->rules();
    }

    protected function messages(): array
    {
        return $this->reviewValidator->messages();
    }

    protected function validationAttributes(): array
    {
        return $this->reviewValidator->attributes();
    }

    private function committees(): Collection
    {
        return Committee::query()
            ->with('legislature.jurisdiction.country')
            ->orderBy('name')
            ->get();
    }

    private function resolveSelectedCommittee(Collection $committees): ?Committee
    {
        if ($this->committee_id === '') {
            return null;
        }

        return $committees->firstWhere('id', (int) $this->committee_id);
    }
}
