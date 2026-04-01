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

test('local pdf extractor also extracts text from a stored docx file', function () {
    Storage::fake('assistant-sources');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'docx-test-');
    $archive = new ZipArchive;
    $opened = $archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    expect($opened)->toBeTrue();

    $archive->addFromString(
        'word/document.xml',
        <<<'XML'
        <w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
            <w:body>
                <w:p><w:r><w:t>Guide line one</w:t></w:r></w:p>
                <w:p><w:r><w:t>Guide line two</w:t></w:r></w:p>
            </w:body>
        </w:document>
        XML,
    );
    $archive->close();

    Storage::disk('assistant-sources')->put('imports/wfd/guide.docx', file_get_contents($temporaryPath));

    if (is_string($temporaryPath) && is_file($temporaryPath)) {
        @unlink($temporaryPath);
    }

    $document = AssistantSourceDocument::factory()->create([
        'storage_path' => 'imports/wfd/guide.docx',
        'metadata' => [
            'disk' => 'assistant-sources',
        ],
    ]);

    $result = (new LocalPdfToTextExtractor('pdftotext'))->extract($document);

    expect($result->status)->toBe('completed')
        ->and($result->content)->toContain('Guide line one')
        ->toContain('Guide line two');
});
