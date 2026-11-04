<?php

use App\Ai\Agents\LegislationSourceExtractorAgent;
use App\Ai\Agents\ReviewAssistantAgent;
use App\Ai\Agents\ReviewDocumentExtractorAgent;
use App\Domain\Analysis\Finding;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\AnalyzeLegislationSource;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\ProcessReviewLegislationSource;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\DocumentPage;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Livewire\Pls\Reviews\StakeholdersPage;
use App\Livewire\Pls\Reviews\WorkflowPage;
use App\Models\User;
use App\Support\PlsAssistant\SourceIdentity;
use App\Support\PlsAssistant\StakeholderSuggestionNormalizer;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

it('serializes strict object schemas at every nesting level', function () {
    $schema = (new ObjectSchema((new LegislationSourceExtractorAgent)->schema(new JsonSchemaTypeFactory)))->toArray();
    $check = function (array $node) use (&$check) {
        if (($node['type'] ?? null) === 'object') {
            expect($node['additionalProperties'] ?? null)->toBeFalse();
            expect($node['required'])->toEqualCanonicalizing(array_keys($node['properties']));
        }
        foreach ($node as $value) {
            if (is_array($value)) {
                $check($value);
            }
        }
    };
    $check($schema);
});

it('supplies all legislation chunks and all laws in every workspace before metadata confirmation', function (string $workspace) {
    $review = plsReview();
    $this->actingAs($review->owner);
    $longText = "Opening provision.\n\n".str_repeat("Ordinary provisions of this demonstration legislation.\n\n", 500).'Final schedule ZEBRA provides an annual independent audit.';
    for ($i = 1; $i <= 4; $i++) {
        $law = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText, 'title' => "Uploaded Act $i"]);
        app(ChunkDocumentText::class)->chunk($law, $longText);
    }
    $foreign = Document::factory()->create(['title' => 'PRIVATE foreign Act', 'document_type' => DocumentType::LegislationText]);
    app(ChunkDocumentText::class)->chunk($foreign, 'PRIVATE foreign text');
    ReviewAssistantAgent::fake(['The final schedule requires an audit.']);
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => $workspace])
        ->call('sendPrompt', 'Explain the final schedule of the uploaded Acts.')->assertHasNoErrors();
    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) use ($longText) {
        $instructions = (string) $prompt->agent->instructions();
        expect($instructions)->toContain('Uploaded Act 1', 'Uploaded Act 4', $longText)
            ->not->toContain('PRIVATE foreign text', 'No legislation is uploaded or linked');

        return true;
    });
})->with(['workflow', 'documents', 'legislation', 'analysis', 'reports']);

it('passes the end of long legislation to metadata extraction', function () {
    $review = plsReview();
    $law = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText]);
    $text = str_repeat("An ordinary source paragraph.\n\n", 700).'CLOSING SCHEDULE: independent annual audit.';
    LegislationSourceExtractorAgent::fake([['title' => 'Demonstration Act', 'legislation_type' => 'act', 'relationship_type' => 'primary']]);
    app(AnalyzeLegislationSource::class)->enrich($law, $review->jurisdiction_id, $text);
    LegislationSourceExtractorAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains($text));
});

it('opens document details and every text section for a viewer and streams the original privately', function () {
    Storage::fake('local');
    $review = plsReview();
    $viewer = User::factory()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $original = 'Original evidence body';
    Storage::disk('local')->put('evidence/source.txt', $original);
    $document = Document::factory()->create([
        'pls_review_id' => $review->id, 'title' => 'Implementation evidence', 'document_type' => DocumentType::ImplementationReport,
        'summary' => 'Summary for review', 'storage_path' => 'evidence/source.txt', 'mime_type' => 'text/plain',
        'metadata' => ['disk' => 'local', 'original_name' => 'source.txt', 'document_analysis' => ['status' => 'saved', 'warnings' => ['Check OCR accuracy'], 'key_themes' => ['Reporting']]],
    ]);
    for ($i = 0; $i < 16; $i++) {
        $document->chunks()->create(['chunk_index' => $i, 'content' => "Source section $i", 'token_count' => 4]);
    }
    $this->actingAs($viewer)->get(route('pls.reviews.documents.show', [$review, $document]))->assertOk()
        ->assertSee('Implementation evidence')->assertSee('Check OCR accuracy')->assertSee('Open original document')
        ->assertSee(route('pls.reviews.documents.original', [$review, $document]));
    Livewire::test(DocumentPage::class, ['review' => $review, 'document' => $document])
        ->call('setPage', 2)->assertSee('Source section 15');
    $this->get(route('pls.reviews.documents.original', [$review, $document]))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertStreamedContent($original);
});

it('rejects cross review document details and originals and unauthorised viewers', function () {
    $review = plsReview();
    $document = Document::factory()->create();
    $this->actingAs($review->owner);
    foreach (['pls.reviews.documents.show', 'pls.reviews.documents.original'] as $route) {
        $this->get(route($route, [$review, $document]))->assertNotFound();
        $this->actingAs(User::factory()->create())->get(route($route, [$review, $document]))->assertForbidden();
        $this->actingAs($review->owner);
    }
});

it('does not render active original content inline and handles missing files', function () {
    Storage::fake('local');
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'storage_path' => 'unsafe.html', 'mime_type' => 'text/html', 'metadata' => ['disk' => 'local']]);
    $this->actingAs($review->owner)->get(route('pls.reviews.documents.original', [$review, $document]))->assertNotFound();
    Storage::disk('local')->put('unsafe.html', '<html><script>alert(1)</script></html>');
    $response = $this->get(route('pls.reviews.documents.original', [$review, $document]))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toStartWith('attachment;');
});

it('prevents client replacement of the assistant conversation identifier', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'workflow'])
        ->set('assistantConversationId', 'foreign-conversation');
})->throws(CannotUpdateLockedPropertyException::class);

it('refuses an oversized request explicitly without dropping legislation', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    config(['pls_assistant.max_context_characters' => 1]);
    ReviewAssistantAgent::fake();
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'workflow'])
        ->call('sendPrompt', 'Explain the current step.')
        ->assertSee('No legislation text has been omitted')->assertSet('assistantInput', 'Explain the current step.');
    ReviewAssistantAgent::assertNeverPrompted();
});

it('returns a guest to their invitation after sign in', function () {
    $review = plsReview();
    $invitee = User::factory()->create();
    $invitation = $review->invitations()->create(['email' => $invitee->email, 'role' => PlsReviewMembershipRole::Viewer, 'invited_by' => $review->owner->id]);
    $url = route('pls.invitations.accept', $invitation->token);
    $this->get($url)->assertRedirect(route('login'))->assertSessionHas('url.intended', $url);
    $this->post(route('login.store'), ['email' => $invitee->email, 'password' => 'password'])->assertRedirect($url);
    $this->get($url)->assertRedirect(route('pls.reviews.workflow', $review));
    expect($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('requires email verification before opening a shared review', function () {
    $review = plsReview();
    $viewer = User::factory()->unverified()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $this->actingAs($viewer)->get(route('pls.reviews.workflow', $review))->assertRedirect(route('verification.notice'));
});

it('accepts temporary files up to the advertised upload limit', function () {
    expect(Validator::make(['file' => UploadedFile::fake()->create('source.pdf', 51200, 'application/pdf')], ['file' => FileUploadConfiguration::rules()])->passes())->toBeTrue();
    expect(Validator::make(['file' => UploadedFile::fake()->create('source.pdf', 51201, 'application/pdf')], ['file' => FileUploadConfiguration::rules()])->fails())->toBeTrue();
});

it('recognizes legislation uploaded through Evidence and schedules full legislation review', function () {
    Queue::fake();
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::GroupReport]);
    app(ChunkDocumentText::class)->chunk($document, 'Demonstration Bill. The final schedule requires annual reports.');
    ReviewDocumentExtractorAgent::fake([[
        'title' => 'Demonstration Bill', 'document_type' => 'legislation_text', 'summary' => 'A demonstration legislative instrument.',
    ]]);
    app()->call([new ProcessReviewDocument($document->id, 'enrich'), 'handle']);
    expect($document->fresh()->document_type)->toBe(DocumentType::LegislationText)
        ->and(data_get($document->fresh()->metadata, 'legislation_analysis.status'))->toBe('processing');
    Queue::assertPushed(ProcessReviewLegislationSource::class, fn ($job) => $job->documentId === $document->id && $job->step === 'enrich');
    $this->actingAs($review->owner)->get(route('pls.reviews.documents', $review))->assertOk()
        ->assertSee('Legislation in this evidence base')->assertSee('Demonstration Bill')
        ->assertSee(route('pls.reviews.documents.show', [$review, $document]));
});

it('distinguishes Ghana bill and debate source identities without inventing enactment dates', function () {
    $analysis = ['title' => 'Affirmative Action Act, 2024', 'legislation_type' => 'act', 'date_enacted' => '2024-07-22', 'relationship_type' => 'primary', 'source_label' => '22 July 2024'];
    $bill = SourceIdentity::guardLegislation($analysis, 'Affirmative Action BILL, 2024 ARRANGEMENT OF CLAUSES A BILL ENTITLED an Act');
    expect($bill['legislation_type'])->toBe('bill')->and($bill['date_enacted'])->toBe('')->and($bill['title'])->toContain('Bill');
    $debate = SourceIdentity::guardLegislation($analysis, 'PARLIAMENT OF GHANA PARLIAMENTARY DEBATES OFFICIAL REPORT CONTENTS Appointment of Justices BILLS CONSIDERATION STAGE Affirmative Action Bill');
    expect($debate['legislation_type'])->toBe('parliamentary_record')->and($debate['relationship_type'])->toBe('related')->and($debate['title'])->not->toContain('Appointment');
});

it('links only real passages within the review and invalidates support when a finding changes', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $passage = 'The committee shall submit an annual report to Parliament.';
    app(ChunkDocumentText::class)->chunk($document, $passage);
    $finding = Finding::factory()->create(['pls_review_id' => $review->id]);
    $component = Livewire::test(DocumentPage::class, compact('review', 'document'))
        ->set('citationFindingId', (string) $finding->id)
        ->set('citationPassage', 'An invented passage not present in the source.')
        ->call('saveCitation')->assertHasErrors('citationPassage')
        ->set('citationPassage', $passage)->call('saveCitation')->assertHasNoErrors();
    expect($finding->fresh()->hasReviewedSupport())->toBeTrue();
    $finding->update(['summary' => 'Changed conclusion that needs another source review.']);
    expect($finding->fresh()->hasReviewedSupport())->toBeFalse();
    $foreign = Finding::factory()->create();
    expect(fn () => $component->set('citationFindingId', (string) $foreign->id)->set('citationPassage', $passage)->call('saveCitation'))->toThrow(ModelNotFoundException::class);
    $this->get(route('pls.reviews.analysis', $review))->assertOk()->assertSee($passage)->assertSee(route('pls.reviews.documents.show', [$review, $document]));
});

it('corrects saved debate classifications while preserving the previous analysis and original source', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    $analysis = ['title' => 'Appointment of Justices Act', 'short_title' => 'Justices Act', 'legislation_type' => 'act', 'date_enacted' => '2024-07-22'];
    $document = Document::factory()->create([
        'pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText,
        'metadata' => ['original_name' => '22 July 2024.pdf', 'legislation_analysis' => $analysis],
    ]);
    app(ChunkDocumentText::class)->chunk($document, 'PARLIAMENTARY DEBATES OFFICIAL REPORT Appointment of Justices. Affirmative Action Bill.');
    $law = Legislation::factory()->create([...$analysis, 'source_document_id' => $document->id]);
    $review->legislation()->attach($law, ['relationship_type' => 'primary']);
    Livewire::test(DocumentPage::class, compact('review', 'document'))->call('correctSourceIdentity')->assertHasNoErrors();
    expect($law->fresh()->legislation_type)->toBe(LegislationType::ParliamentaryRecord)
        ->and($law->fresh()->date_enacted)->toBeNull()
        ->and($law->fresh()->title)->toBe('Parliamentary debates — 22 July 2024.pdf')
        ->and($review->legislation()->first()->pivot->relationship_type)->toBe('related')
        ->and($document->fresh()->storage_path)->toBe($document->storage_path)
        ->and(data_get($document->fresh()->metadata, 'source_identity_before_correction.title'))->toBe('Appointment of Justices Act');
});

it('dismisses a grouped stakeholder suggestion in every source without touching unrelated suggestions', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    $documents = collect(['FIDA Ghana', 'International Federation of Women Lawyers (FIDA Ghana)'])->map(function ($name, $index) use ($review) {
        return Document::factory()->create(['pls_review_id' => $review->id, 'metadata' => ['document_analysis' => ['stakeholder_suggestions' => [
            ['id' => 'fida-'.$index, 'name' => $name, 'kind' => 'stakeholder', 'status' => 'suggested'],
            ['id' => 'other-'.$index, 'name' => 'Unrelated organization', 'kind' => 'stakeholder', 'status' => 'suggested'],
        ]]]]);
    });
    Livewire::test(StakeholdersPage::class, compact('review'))->call('dismissSuggestedStakeholder', 'fida-0')->assertHasNoErrors();
    foreach ($documents as $document) {
        expect(data_get($document->fresh()->metadata, 'document_analysis.stakeholder_suggestions.0.status'))->toBe('dismissed')
            ->and(data_get($document->fresh()->metadata, 'document_analysis.stakeholder_suggestions.1.status'))->toBe('suggested');
    }
});

it('prevents viewers from confirming sources or adding reviewed citations', function () {
    $review = plsReview();
    $viewer = User::factory()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $this->actingAs($viewer);
    foreach (['saveCitation', 'markReviewed', 'correctSourceIdentity'] as $method) {
        Livewire::test(DocumentPage::class, compact('review', 'document'))->call($method)->assertForbidden();
    }
});

it('saves report outline content and preserves prior versions with private read and export access', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    $component = Livewire::test(ReportsPage::class, compact('review'))
        ->call('receiveReportOutline', "# Ghana report outline\n\n## Scope\nPurpose: Examine reporting\nMaterial: Legislation and evidence\nLimitations: Verify citations")
        ->call('prepareReportFromOutline');
    expect($component->get('reportContent'))->not->toBe('');
    $component->set('reportTitle', 'Ghana draft report')->call('storeReport')->assertHasNoErrors();
    $report = $review->reports()->latest('id')->firstOrFail();
    $original = $report->content;
    expect($original)->not->toBeEmpty();
    $component->call('startEditingReport', $report->id)->set('reportContent', '## Reviewed draft\n\nChecked source [details]('.route('pls.reviews.documents', $review).')')->call('updateReport')->assertHasNoErrors();
    expect($report->fresh()->content_versions[0]['content'])->toBe($original);
    $this->get(route('pls.reviews.reports.show', [$review, $report]))->assertOk()->assertSee('Reviewed draft');
    $this->get(route('pls.reviews.reports.export', [$review, $report]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $other = plsReview();
    $this->actingAs($other->owner)->get(route('pls.reviews.reports.show', [$other, $report]))->assertNotFound();
    $this->get(route('pls.reviews.reports.export', [$other, $report]))->assertNotFound();
});

it('groups repeated stakeholder names and explicit aliases across sources', function () {
    $normalizer = app(StakeholderSuggestionNormalizer::class);
    $items = collect([
        ['id' => 'a', 'name' => 'FIDA Ghana', 'kind' => 'stakeholder'],
        ['id' => 'b', 'name' => 'International Federation of Women Lawyers (FIDA Ghana)', 'kind' => 'stakeholder'],
        ['id' => 'c', 'name' => 'Affirmative Action Bill Coalition', 'kind' => 'stakeholder'],
        ['id' => 'd', 'name' => 'Affirmative Action Law Coalition', 'kind' => 'stakeholder'],
        ['id' => 'e', 'name' => 'An unrelated organization', 'kind' => 'stakeholder'],
    ]);
    $groups = $normalizer->group($items);
    expect($groups)->toHaveCount(3)->and($groups->first()['sources'])->toHaveCount(2);
});

it('counts confirmed workflow stages rather than the current stage number', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    expect($review->progressPercentage())->toBe(0);
    Livewire::test(WorkflowPage::class, compact('review'))->call('completeCurrentStep')->assertHasNoErrors();
    expect($review->fresh()->progressPercentage())->toBe(9)->and($review->fresh()->current_step_number)->toBe(2);
});

it('surfaces blank reporting templates and supports document text and evidence search', function () {
    $review = plsReview();
    $this->actingAs($review->owner);
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'title' => 'Annual Reporting Template', 'metadata' => ['document_analysis' => ['warnings' => ['Document is a blank reporting template without filled data.']]]]);
    app(ChunkDocumentText::class)->chunk($document, 'Annual template includes fields for gender-equity reporting.');
    expect($document->isReportingTemplate())->toBeTrue();
    Livewire::test(DocumentsPage::class, compact('review'))->set('search', 'Annual Reporting')->assertSee('Template — no reported results')->set('search', 'unrelated-title')->assertDontSee('Annual Reporting Template');
    Livewire::test(DocumentPage::class, compact('review', 'document'))->set('search', 'gender-equity')->assertSee('Annual template includes fields');
});
