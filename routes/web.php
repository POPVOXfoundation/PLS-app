<?php

use App\Domain\Reviews\PlsReview;
use App\Livewire\Dashboard;
use App\Livewire\Pls\Reviews\AnalysisPage;
use App\Livewire\Pls\Reviews\CollaboratorsPage;
use App\Livewire\Pls\Reviews\ConsultationsPage;
use App\Livewire\Pls\Reviews\Create;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Livewire\Pls\Reviews\Index;
use App\Livewire\Pls\Reviews\LegislationPage;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Livewire\Pls\Reviews\StakeholdersPage;
use App\Livewire\Pls\Reviews\WorkflowPage;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('pls/reviews', Index::class)->can('viewAny', PlsReview::class)->name('pls.reviews.index');
    Route::get('pls/reviews/create', Create::class)->can('create', PlsReview::class)->name('pls.reviews.create');
    Route::prefix('pls/reviews/{review}')->group(function () {
        Route::get('/', fn (PlsReview $review) => to_route('pls.reviews.workflow', ['review' => $review]))
            ->can('view', 'review')
            ->name('pls.reviews.show');
        Route::get('/workflow', WorkflowPage::class)->can('view', 'review')->name('pls.reviews.workflow');
        Route::get('/collaborators', CollaboratorsPage::class)->can('view', 'review')->name('pls.reviews.collaborators');
        Route::get('/legislation', LegislationPage::class)->can('view', 'review')->name('pls.reviews.legislation');
        Route::get('/documents', DocumentsPage::class)->can('view', 'review')->name('pls.reviews.documents');
        Route::get('/stakeholders', StakeholdersPage::class)->can('view', 'review')->name('pls.reviews.stakeholders');
        Route::get('/consultations', ConsultationsPage::class)->can('view', 'review')->name('pls.reviews.consultations');
        Route::get('/analysis', AnalysisPage::class)->can('view', 'review')->name('pls.reviews.analysis');
        Route::get('/reports', ReportsPage::class)->can('view', 'review')->name('pls.reviews.reports');
    });
});

require __DIR__.'/settings.php';
