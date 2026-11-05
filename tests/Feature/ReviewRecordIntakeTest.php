<?php

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Jobs\ProcessReviewDocument;
use App\Livewire\Pls\Reviews\RecordIntake;
use App\Livewire\Pls\Reviews\SourcePage;
use App\Models\User;
use App\Support\Documents\ReviewRecordSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
    Storage::fake('local');
    config()->set('filesystems.default', 'local');
    Bus::fake();
    Http::preventStrayRequests();
});

function recordCsv(array $rows): UploadedFile
{
    $stream = fopen('php://temp', 'w+');
    fputcsv($stream, ReviewRecordSpreadsheet::COLUMNS, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($stream, array_map(fn ($key) => $row[$key] ?? '', ReviewRecordSpreadsheet::COLUMNS), ',', '"', '');
    }
    rewind($stream);
    $content = stream_get_contents($stream);
    fclose($stream);

    return UploadedFile::fake()->createWithContent('record.csv', $content);
}

test('source links remain unread references and can receive an original without a second record', function () {
    $review = plsReview();
    Livewire::test(RecordIntake::class, ['review' => $review])->set('title', 'Public implementation record')->set('url', 'https://example.com/record')
        ->call('previewImport')->assertHasNoErrors()->call('import')->assertHasNoErrors();
    $document = $review->documents()->sole();
    expect($document->storage_path)->toBeNull()->and($document->originalUrl())->toBe('https://example.com/record');
    expect($document->processingLabel())->toContain('Reference only');
    Bus::assertNothingDispatched();
    Livewire::test(SourcePage::class, ['review' => $review, 'document' => $document])
        ->set('sourceUpload', UploadedFile::fake()->createWithContent('record.txt', 'Complete original source text.'))
        ->call('attachOriginal')->assertHasNoErrors();
    expect($review->documents()->count())->toBe(1);
    expect($document->fresh()->storage_path)->not->toBeNull();
    Bus::assertDispatched(ProcessReviewDocument::class);
});

test('spreadsheet intake links a submission to one canonical source document', function () {
    $review = plsReview();
    $sheet = recordCsv([['title' => 'Witness submission', 'filename' => 'witness.txt', 'type' => 'consultation_submission', 'date' => '2026-11-05', 'stakeholder' => 'Witness group', 'notes' => 'Recorded contribution']]);
    Livewire::test(RecordIntake::class, ['review' => $review])->set('files', [UploadedFile::fake()->createWithContent('witness.txt', 'Witness source text')])
        ->set('spreadsheet', $sheet)->call('previewImport')->assertHasNoErrors()->call('import')->assertHasNoErrors();
    $document = $review->documents()->sole();
    expect($review->submissions()->sole()->document_id)->toBe($document->id);
    expect($document->submissions()->with('stakeholder')->sole()->stakeholder->name)->toBe('Witness group');
    $this->get(route('pls.reviews.sources', $review))->assertOk()->assertSee('Witness submission')->assertSee('Witness group');
});

test('invalid dates missing files and malformed URLs block the entire batch', function () {
    $review = plsReview();
    $sheet = recordCsv([
        ['title' => 'Valid reference', 'url' => 'https://example.com/valid', 'type' => 'policy_report'],
        ['title' => 'Invalid record', 'filename' => 'missing.txt', 'url' => 'javascript:alert(1)', 'type' => 'policy_report', 'date' => 'yesterday'],
    ]);
    Livewire::test(RecordIntake::class, ['review' => $review])->set('spreadsheet', $sheet)->call('previewImport')
        ->assertSee('Choose exactly one file')->call('import')->assertHasErrors('files');
    expect($review->documents()->count())->toBe(0);
});

test('duplicates require explicit confirmation and repeated clicks cannot silently import them again', function () {
    $review = plsReview();
    $component = Livewire::test(RecordIntake::class, ['review' => $review])->set('title', 'Source')->set('url', 'https://example.com/source')
        ->call('previewImport')->call('import')->assertHasNoErrors();
    $component->set('title', 'Source again')->set('url', 'https://example.com/source')->call('previewImport')->call('import')->assertHasErrors('files');
    expect($review->documents()->count())->toBe(1);
    $component->set('allowDuplicates', true)->call('import')->assertHasNoErrors();
    expect($review->documents()->count())->toBe(2);
    $component->call('import');
    expect($review->documents()->count())->toBe(2);
});

test('viewers can use the register but cannot import or attach files', function () {
    $review = plsReview();
    $viewer = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $this->actingAs($viewer);
    $this->get(route('pls.reviews.sources', $review))->assertOk();
    Livewire::test(RecordIntake::class, ['review' => $review])->call('previewImport')->assertForbidden();
});

test('complete register exports include legislation and guard spreadsheet formulas', function (string $format) {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'title' => '=HYPERLINK("https://example.com")', 'document_type' => DocumentType::LegislationText]);
    $response = $this->get(route('pls.reviews.record.export', [$review, 'format' => $format]))->assertOk()->assertDownload();
    $path = tempnam(sys_get_temp_dir(), 'pls-register-test-');
    file_put_contents($path, $response->getContent());
    $reader = SimpleExcelReader::create($path, $format)->keepFormulas();
    $rows = $reader->getRows()->all();
    expect($rows)->toHaveCount(1);
    expect($rows[0]['Title'])->toStartWith("'=");
    expect($rows[0]['Source details'])->toBe(route('pls.reviews.documents.show', [$review, $document]));
    $reader->close();
    unlink($path);
    $this->actingAs(User::factory()->reviewer()->create())->get(route('pls.reviews.record.export', [$review, 'format' => $format]))->assertForbidden();
})->with(['csv', 'xlsx']);

test('xlsx metadata is read through the Laravel integration without evaluating formulas', function () {
    $path = tempnam(sys_get_temp_dir(), 'pls-intake-test-');
    $writer = SimpleExcelWriter::create($path, 'xlsx');
    $writer->addHeader(ReviewRecordSpreadsheet::COLUMNS)->addRow(['Report', '', 'https://example.com', 'policy_report', '2026-11-05', 'Office', '', '=SUM(1,2)']);
    $writer->close();
    $rows = app(ReviewRecordSpreadsheet::class)->read($path, 'xlsx');
    expect($rows[0]['notes'])->toBe('=SUM(1,2)');
    unlink($path);
});

test('oversized metadata batches are rejected before records are written', function () {
    $review = plsReview();
    $rows = array_fill(0, 251, ['title' => 'Record', 'url' => 'https://example.com', 'type' => 'policy_report']);
    Livewire::test(RecordIntake::class, ['review' => $review])->set('spreadsheet', recordCsv($rows))->call('previewImport')->assertHasErrors('spreadsheet');
    expect($review->documents()->count())->toBe(0);
});

test('an observer failure after uploading rolls back the batch and removes the allocated original file', function () {
    $review = plsReview();
    Event::listen('eloquent.created: '.Document::class, function (): void {
        throw new RuntimeException('Synthetic database failure after original storage');
    });
    try {
        Livewire::test(RecordIntake::class, ['review' => $review])->set('files', [UploadedFile::fake()->createWithContent('rollback.txt', 'Source content')])
            ->call('previewImport')->call('import')->assertHasErrors('files');
        expect($review->documents()->count())->toBe(0);
        expect(Storage::disk('local')->allFiles('pls/reviews'))->toBe([]);
    } finally {
        Event::forget('eloquent.created: '.Document::class);
    }
});
