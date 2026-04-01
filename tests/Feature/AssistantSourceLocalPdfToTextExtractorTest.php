<?php

use App\Domain\Documents\AssistantSourceDocument;
use App\Support\PlsAssistant\LocalPdfToTextExtractor;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

test('local pdf extractor cleans extracted text from a stored PDF', function () {
    Storage::fake('assistant-sources');
    Storage::disk('assistant-sources')->put('imports/wfd/guide.pdf', 'pdf placeholder');

    $document = AssistantSourceDocument::factory()->create([
        'storage_path' => 'imports/wfd/guide.pdf',
        'metadata' => [
            'disk' => 'assistant-sources',
        ],
    ]);

    Process::fake([
        '*pdftotext*' => Process::result(output: "Guide line 1\n\n12\n\nGuide line 2"),
    ]);

    $result = (new LocalPdfToTextExtractor('pdftotext'))->extract($document);

    expect($result->status)->toBe('completed')
        ->and($result->driver)->toBe('local')
        ->and($result->content)->toContain('Guide line 1')
        ->toContain('Guide line 2')
        ->not->toContain("\n12\n");

    Process::assertRan(fn ($process) => str_contains($process->command, '-layout -nopgbrk -enc UTF-8'));
});

test('local pdf extractor reports pdftotext failures', function () {
    Storage::fake('assistant-sources');
    Storage::disk('assistant-sources')->put('imports/wfd/guide.pdf', 'pdf placeholder');

    $document = AssistantSourceDocument::factory()->create([
        'storage_path' => 'imports/wfd/guide.pdf',
        'metadata' => [
            'disk' => 'assistant-sources',
        ],
    ]);

    Process::fake([
        '*pdftotext*' => Process::result(errorOutput: 'binary missing', exitCode: 1),
    ]);

    $result = (new LocalPdfToTextExtractor('pdftotext'))->extract($document);

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('binary missing');
});

test('local pdf extractor fails when no usable text is returned', function () {
    Storage::fake('assistant-sources');
    Storage::disk('assistant-sources')->put('imports/wfd/guide.pdf', 'pdf placeholder');

    $document = AssistantSourceDocument::factory()->create([
        'storage_path' => 'imports/wfd/guide.pdf',
        'metadata' => [
            'disk' => 'assistant-sources',
        ],
    ]);

    Process::fake([
        '*pdftotext*' => Process::result(output: "\n\f\n"),
    ]);

    $result = (new LocalPdfToTextExtractor('pdftotext'))->extract($document);

    expect($result->status)->toBe('failed')
        ->and($result->error)->toContain('No usable text');
});
