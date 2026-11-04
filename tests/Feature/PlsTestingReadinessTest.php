<?php

use App\Ai\Agents\LegislationSourceExtractorAgent;
use App\Ai\Agents\ReviewAssistantAgent;
use App\Ai\Agents\ReviewDocumentExtractorAgent;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\AnalyzeLegislationSource;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\ProcessReviewLegislationSource;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\DocumentPage;
use App\Models\User;
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
