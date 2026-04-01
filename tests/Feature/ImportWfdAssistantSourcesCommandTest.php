<?php

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Enums\AssistantSourceScope;
use App\Support\PlsAssistant\ReviewAssistantGroundingRepository;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

test('it imports configured WFD PDFs into global assistant source documents', function () {
    $directory = storage_path('framework/testing/wfd-import');
    File::ensureDirectoryExists($directory);

    $guidePath = $directory.'/guide.pdf';
    $manualPath = $directory.'/manual.pdf';

    File::put($guidePath, 'guide pdf placeholder');
    File::put($manualPath, 'manual pdf placeholder');

    config()->set('pls_assistant.wfd_import.documents', [
        [
            'key' => 'guide-2017',
            'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
            'summary' => 'Guide summary',
            'published_at' => '2017-11',
            'path' => $guidePath,
        ],
        [
            'key' => 'manual-2023',
            'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
            'summary' => 'Manual summary',
            'published_at' => '2023-07',
            'path' => $manualPath,
        ],
    ]);

    Process::fake([
        "*pdftotext*{$guidePath}*" => Process::result(output: "Guide line 1\n\n12\n\nGuide line 2"),
        "*pdftotext*{$manualPath}*" => Process::result(output: "Manual line 1\n\nManual line 2"),
    ]);

    $this->artisan('pls:assistant-sources:import-wfd')
        ->assertSuccessful();

    expect(AssistantSourceDocument::query()
        ->where('scope', AssistantSourceScope::Global)
        ->count())->toBe(2);

    $guide = AssistantSourceDocument::query()
        ->where('title', 'Post-Legislative Scrutiny Guide for Parliaments')
        ->sole();

    $manual = AssistantSourceDocument::query()
        ->where('title', 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments')
        ->sole();

    expect($guide->content)->toContain('Guide line 1')
        ->toContain('Guide line 2')
        ->not->toContain("\n12\n")
        ->and($guide->storage_path)->toBe($guidePath)
        ->and($guide->metadata['source_type'])->toBe('wfd_pdf')
        ->and($manual->content)->toContain('Manual line 1')
        ->toContain('Manual line 2');

    $review = plsReview([
        'title' => 'Grounding smoke review',
    ]);

    $globalGrounding = app(ReviewAssistantGroundingRepository::class)
        ->forPrompt($review->fresh(), 'What are the main steps in a PLS inquiry?')['global'];

    expect($globalGrounding)->toHaveCount(2)
        ->and(collect($globalGrounding)->pluck('label')->all())->toContain(
            'Post-Legislative Scrutiny Guide for Parliaments',
            'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
        );
});

test('it updates existing WFD source records instead of duplicating them', function () {
    $directory = storage_path('framework/testing/wfd-import');
    File::ensureDirectoryExists($directory);

    $guidePath = $directory.'/guide-update.pdf';
    $manualPath = $directory.'/manual-update.pdf';

    File::put($guidePath, 'guide pdf placeholder');
    File::put($manualPath, 'manual pdf placeholder');

    AssistantSourceDocument::factory()->create([
        'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
        'scope' => AssistantSourceScope::Global,
        'storage_path' => 'old-guide-path.pdf',
        'content' => 'Old guide content',
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
        'scope' => AssistantSourceScope::Global,
        'storage_path' => 'old-manual-path.pdf',
        'content' => 'Old manual content',
    ]);

    config()->set('pls_assistant.wfd_import.documents', [
        [
            'key' => 'guide-2017',
            'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
            'summary' => 'Guide summary',
            'published_at' => '2017-11',
            'path' => $guidePath,
        ],
        [
            'key' => 'manual-2023',
            'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
            'summary' => 'Manual summary',
            'published_at' => '2023-07',
            'path' => $manualPath,
        ],
    ]);

    Process::fake([
        "*pdftotext*{$guidePath}*" => Process::result(output: 'Updated guide content'),
        "*pdftotext*{$manualPath}*" => Process::result(output: 'Updated manual content'),
    ]);

    $this->artisan('pls:assistant-sources:import-wfd')
        ->assertSuccessful();

    expect(AssistantSourceDocument::query()
        ->where('scope', AssistantSourceScope::Global)
        ->count())->toBe(2)
        ->and(AssistantSourceDocument::query()
            ->where('title', 'Post-Legislative Scrutiny Guide for Parliaments')
            ->sole()
            ->content)->toBe('Updated guide content')
        ->and(AssistantSourceDocument::query()
            ->where('title', 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments')
            ->sole()
            ->content)->toBe('Updated manual content');
});
