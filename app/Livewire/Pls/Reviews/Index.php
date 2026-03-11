<?php

namespace App\Livewire\Pls\Reviews;

use App\Domain\Reviews\PlsReview;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use WithPagination;

    public string $viewMode = 'cards';

    public function render(): View
    {
        return view('livewire.pls.reviews.index', [
            'reviews' => $this->reviews(),
        ])->layout('layouts.app', [
            'title' => __('PLS Reviews'),
        ]);
    }

    private function reviews(): LengthAwarePaginator
    {
        return PlsReview::query()
            ->with([
                'committee:id,legislature_id,name',
                'legislature:id,jurisdiction_id,name',
                'jurisdiction:id,country_id,name',
                'country:id,name',
                'steps:id,pls_review_id,step_number,step_key,status,started_at,completed_at,notes',
            ])
            ->withCount([
                'legislation',
                'documents',
                'findings',
                'recommendations',
            ])
            ->orderByDesc('start_date')
            ->orderByDesc('created_at')
            ->paginate(8);
    }
}
