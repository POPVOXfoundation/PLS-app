<?php

use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use App\Domain\Documents\Enums\DocumentType;

it('splits raw document text into ordered persisted chunks', function () {
    $document = promptNineMakeDocument();

    $chunks = app(ChunkDocumentText::class)->chunk($document, implode("\n\n", [
        str_repeat('public finance oversight ', 18),
        str_repeat('committee evidence implementation reporting ', 16),
        str_repeat('follow up recommendations scrutiny findings ', 16),
    ]), 120);

    expect($chunks->count())->toBeGreaterThan(1)
        ->and($chunks->pluck('chunk_index')->all())->toBe(range(0, $chunks->count() - 1))
        ->and($chunks->every(fn (DocumentChunk $chunk): bool => $chunk->token_count !== null && $chunk->token_count > 0))->toBeTrue()
        ->and($chunks->every(fn (DocumentChunk $chunk): bool => $chunk->embedding === null))->toBeTrue()
        ->and($chunks->first()->metadata['strategy'])->toBe('paragraph_window_v1');

    $document->refresh();

    expect($document->chunks)->toHaveCount($chunks->count())
        ->and($document->chunks->first()->chunk_index)->toBe(0)
        ->and($document->chunks->last()->chunk_index)->toBe($chunks->count() - 1);
});

it('replaces existing document chunks when text is re-ingested', function () {
    $document = promptNineMakeDocument();

    DocumentChunk::factory()->count(3)->create([
        'document_id' => $document->id,
    ]);

    $chunks = app(ChunkDocumentText::class)->chunk(
        $document,
        str_repeat('implementation monitoring delegated legislation ', 20),
        150,
    );

    expect($chunks)->not->toBeEmpty();
    $this->assertDatabaseCount('document_chunks', $chunks->count());

    expect($document->fresh()->chunks->pluck('chunk_index')->all())->toBe(range(0, $chunks->count() - 1));
});

it('clears stored chunks when blank text is provided', function () {
    $document = promptNineMakeDocument();

    DocumentChunk::factory()->count(2)->create([
        'document_id' => $document->id,
    ]);

    $chunks = app(ChunkDocumentText::class)->chunk($document, " \n\n\t ");

    expect($chunks)->toBeEmpty();
    expect($document->fresh()->chunks)->toBeEmpty();
    $this->assertDatabaseCount('document_chunks', 0);
});

function promptNineMakeDocument(): Document
{
    $review = plsReview();

    return Document::factory()->create([
        'pls_review_id' => $review->id,
        'document_type' => DocumentType::CommitteeReport,
        'title' => 'Committee Background Briefing',
    ]);
}
