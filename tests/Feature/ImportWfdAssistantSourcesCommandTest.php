<?php

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Enums\AssistantSourceScope;
use App\Jobs\ExtractAssistantSourceText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('it stages WFD PDFs into Laravel storage and queues extraction jobs', function () {
    Queue::fake();
    Storage::fake('assistant-sources');

    config()->set('queue.default', 'redis');

    $directory = storage_path('framework/testing/wfd-import');
    File::ensureDirectoryExists($directory);

    $guidePath = $directory.'/guide.pdf';
    $manualPath = $directory.'/manual.pdf';

    File::put($guidePath, 'guide pdf placeholder');
    File::put($manualPath, 'manual pdf placeholder');

    config()->set('pls_assistant.assistant_sources.source_disk', 'assistant-sources');
    config()->set('pls_assistant.assistant_sources.extractor', 'local');
    config()->set('pls_assistant.assistant_sources.wfd_documents', [
        'guide' => [
            'key' => 'guide-2017',
            'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
            'summary' => 'Guide summary',
            'published_at' => '2017-11',
            'storage_path' => 'imports/wfd/guide.pdf',
        ],
        'manual' => [
            'key' => 'manual-2023',
            'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
            'summary' => 'Manual summary',
            'published_at' => '2023-07',
            'storage_path' => 'imports/wfd/manual.pdf',
        ],
    ]);

    $this->artisan('pls:assistant-sources:import-wfd', [
        '--guide-path' => $guidePath,
        '--manual-path' => $manualPath,
    ])
        ->expectsOutputToContain('[CREATED] Post-Legislative Scrutiny Guide for Parliaments')
        ->expectsOutputToContain('Stored file: assistant-sources:imports/wfd/guide.pdf')
        ->expectsOutputToContain('Extraction status: QUEUED')
        ->assertSuccessful();

    Storage::disk('assistant-sources')->assertExists([
        'imports/wfd/guide.pdf',
        'imports/wfd/manual.pdf',
    ]);

    expect(AssistantSourceDocument::query()
        ->where('scope', AssistantSourceScope::Global)
        ->count())->toBe(2);

    $guide = AssistantSourceDocument::query()
        ->where('title', 'Post-Legislative Scrutiny Guide for Parliaments')
        ->sole();

    $manual = AssistantSourceDocument::query()
        ->where('title', 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments')
        ->sole();

    expect($guide->storage_path)->toBe('imports/wfd/guide.pdf')
        ->and($guide->mime_type)->toBe('application/pdf')
        ->and($guide->metadata['disk'])->toBe('assistant-sources')
        ->and(data_get($guide->metadata, 'extraction.status'))->toBe('pending')
        ->and($manual->storage_path)->toBe('imports/wfd/manual.pdf')
        ->and($manual->metadata['disk'])->toBe('assistant-sources');

    Queue::assertPushed(ExtractAssistantSourceText::class, 2);
});

test('it updates existing WFD records in place and migrates them to canonical storage paths', function () {
    Queue::fake();
    Storage::fake('assistant-sources');

    $directory = storage_path('framework/testing/wfd-import');
    File::ensureDirectoryExists($directory);

    $guidePath = $directory.'/guide-update.pdf';
    $manualPath = $directory.'/manual-update.pdf';

    File::put($guidePath, 'guide pdf placeholder');
    File::put($manualPath, 'manual pdf placeholder');

    AssistantSourceDocument::factory()->create([
        'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
        'scope' => AssistantSourceScope::Global,
        'storage_path' => '/Users/bryan/Downloads/guide.pdf',
        'content' => 'Old guide content',
        'metadata' => [],
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
        'scope' => AssistantSourceScope::Global,
        'storage_path' => '/Users/bryan/Downloads/manual.pdf',
        'content' => 'Old manual content',
        'metadata' => [],
    ]);

    config()->set('pls_assistant.assistant_sources.source_disk', 'assistant-sources');
    config()->set('pls_assistant.assistant_sources.wfd_documents', [
        'guide' => [
            'key' => 'guide-2017',
            'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
            'summary' => 'Guide summary',
            'published_at' => '2017-11',
            'storage_path' => 'imports/wfd/guide.pdf',
        ],
        'manual' => [
            'key' => 'manual-2023',
            'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
            'summary' => 'Manual summary',
            'published_at' => '2023-07',
            'storage_path' => 'imports/wfd/manual.pdf',
        ],
    ]);

    $this->artisan('pls:assistant-sources:import-wfd', [
        '--guide-path' => $guidePath,
        '--manual-path' => $manualPath,
    ])->assertSuccessful();

    expect(AssistantSourceDocument::query()
        ->where('scope', AssistantSourceScope::Global)
        ->count())->toBe(2)
        ->and(AssistantSourceDocument::query()
            ->where('title', 'Post-Legislative Scrutiny Guide for Parliaments')
            ->sole()
            ->storage_path)->toBe('imports/wfd/guide.pdf')
        ->and(AssistantSourceDocument::query()
            ->where('title', 'Post-Legislative Scrutiny Guide for Parliaments')
            ->sole()
            ->content)->toBe('Old guide content')
        ->and(AssistantSourceDocument::query()
            ->where('title', 'Post-Legislative Scrutiny Guide for Parliaments')
            ->sole()
            ->metadata['disk'])->toBe('assistant-sources');
});

test('it fails fast when neither a local bootstrap file nor a stored canonical file exists', function () {
    Queue::fake();
    Storage::fake('assistant-sources');

    config()->set('pls_assistant.assistant_sources.source_disk', 'assistant-sources');
    config()->set('pls_assistant.assistant_sources.wfd_documents', [
        'guide' => [
            'key' => 'guide-2017',
            'title' => 'Post-Legislative Scrutiny Guide for Parliaments',
            'summary' => 'Guide summary',
            'published_at' => '2017-11',
            'storage_path' => 'imports/wfd/guide.pdf',
            'bootstrap_path' => '/missing/guide.pdf',
        ],
        'manual' => [
            'key' => 'manual-2023',
            'title' => 'Parliamentary Innovation through Post-Legislative Scrutiny: Manual for Parliaments',
            'summary' => 'Manual summary',
            'published_at' => '2023-07',
            'storage_path' => 'imports/wfd/manual.pdf',
            'bootstrap_path' => '/missing/manual.pdf',
        ],
    ]);

    $this->artisan('pls:assistant-sources:import-wfd')
        ->expectsOutputToContain('WFD assistant source file not found')
        ->assertFailed();

    Queue::assertNothingPushed();
});
