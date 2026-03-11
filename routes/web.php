<?php

use App\Domain\Reviews\PlsReview;
use App\Livewire\Dashboard;
use App\Livewire\Pls\Reviews\Create;
use App\Livewire\Pls\Reviews\Index;
use App\Livewire\Pls\Reviews\Show;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('pls/reviews', Index::class)->can('viewAny', PlsReview::class)->name('pls.reviews.index');
    Route::get('pls/reviews/create', Create::class)->can('create', PlsReview::class)->name('pls.reviews.create');
    Route::get('pls/reviews/{review}', Show::class)->can('view', 'review')->name('pls.reviews.show');
});

require __DIR__.'/settings.php';
