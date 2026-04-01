<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use App\Domain\Documents\Enums\AssistantSourceScope;
use App\Domain\Documents\Enums\DocumentType;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Models\User;
use App\Support\PlsAssistant\PlaybookRepository;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the configured role and prompts for the current tab', function (string $workspaceKey, string $role, array $prompts) {
    $review = plsReview([
        'title' => 'Review workspace assistant framing',
    ]);

    $component = Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => $workspaceKey,
    ])->assertSee($role);

    foreach ($prompts as $prompt) {
        $component->assertSee($prompt);
    }
})->with([
    'workflow' => [
        'workflow',
        'Process Guide',
        [
            'Summarize the current step.',
            'Explain what comes next.',
            'Turn the current workflow state into a short update.',
        ],
    ],
    'documents' => [
        'documents',
        'Document Intelligence Assistant',
        [
            'Summarize the uploaded documents.',
            'What documents look missing so far?',
            'Draft a short document status update.',
        ],
    ],
    'reports' => [
        'reports',
        'Report Drafting Assistant',
        [
            'Summarize the current report status.',
            'Explain the current government response status.',
            'Suggest a report structure from the current record.',
        ],
    ],
]);

test('assistant intro changes by tab', function () {
    $review = plsReview([
        'title' => 'Assistant intro review',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])->assertSee('Ask about the current workflow stage');

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'documents',
    ])->assertSee('Ask about uploaded materials');
});

test('assistant composer submits on enter while preserving shift enter for new lines', function () {
    $review = plsReview([
        'title' => 'Composer enter key behavior',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])
        ->assertSeeHtml('submit="enter"')
        ->assertDontSeeHtml('x-on:keydown=');
});

test('assistant can reset only the saved conversation for the current review', function () {
    $firstReview = plsReview([
        'title' => 'First resettable review',
    ]);
    $secondReview = plsReview([
        'title' => 'Second resettable review',
    ]);

    ReviewAssistantAgent::fake([
        'First workflow response',
        'Second workflow response',
    ]);

    $firstComponent = Livewire::test(AssistantSidebar::class, [
        'review' => $firstReview,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'Summarize the current step.');

    Livewire::test(AssistantSidebar::class, [
        'review' => $secondReview,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'Summarize the current step.');

    expect(DB::table('agent_conversations')->count())->toBe(2)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(4);

    $firstConversationId = DB::table('agent_conversations')
        ->where('pls_review_id', $firstReview->id)
        ->value('id');

    $secondConversationId = DB::table('agent_conversations')
        ->where('pls_review_id', $secondReview->id)
        ->value('id');

    $firstComponent->call('resetAssistantConversation')
        ->assertSet('assistantConversationId', null)
        ->assertSet('assistantMessages', [])
        ->assertSet('assistantInput', '')
        ->assertSet('assistantError', null)
        ->assertSee('How this assistant can help here');

    expect(DB::table('agent_conversations')->count())->toBe(1)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(2)
        ->and(DB::table('agent_conversations')->where('id', $firstConversationId)->exists())->toBeFalse()
        ->and(DB::table('agent_conversations')->where('id', $secondConversationId)->exists())->toBeTrue();
});

test('workflow prompts compile current step progress and shared guidance into the agent instructions', function () {
    $review = plsReview([
        'title' => 'Review of access to information compliance',
        'description' => 'Examines disclosure timeliness and implementation bottlenecks.',
    ]);

    ReviewAssistantAgent::fake([
        'You are in step 1. Confirm the review scope, then move into background evidence collection.',
    ]);

    $component = Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])
        ->set('assistantInput', 'Summarize the current step.')
        ->call('submitAssistantPrompt')
        ->assertSet('assistantInput', '');

    expect($component->get('assistantMessages'))->toEqual([
        [
            'role' => 'user',
            'content' => 'Summarize the current step.',
        ],
        [
            'role' => 'assistant',
            'content' => 'You are in step 1. Confirm the review scope, then move into background evidence collection.',
        ],
    ]);

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) use ($review) {
        expect((string) $prompt->agent->instructions())
            ->toContain('You are the PLS Bot assistant.')
            ->toContain('Active tab: Workflow')
            ->toContain('Tab role: Process Guide')
            ->toContain('Playbook version: '.app(PlaybookRepository::class)->versionForTab('workflow'))
            ->toContain('Review title: '.$review->title)
            ->toContain('Progress: Step 1 of 11')
            ->toContain('Current step: Define the objectives and scope of PLS')
            ->toContain('Focus: Define the review scope')
            ->toContain('Suggested action: Link the governing law and upload the initial briefing, bill text, or background pack.');

        return $prompt->contains('Summarize the current step.');
    });
});

test('documents prompts are grounded in the current review record', function () {
    $review = plsReview([
        'title' => 'Review of implementation records',
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Implementation Progress Report',
        'document_type' => DocumentType::ImplementationReport,
        'summary' => 'Tracks implementation delays across ministries.',
    ]);

    ReviewAssistantAgent::fake([
        'There is one implementation report attached so far.',
    ]);

    $component = Livewire::test(AssistantSidebar::class, [
        'review' => $review->fresh(),
        'workspaceKey' => 'documents',
    ])
        ->call('sendPrompt', 'Summarize the uploaded documents.');

    expect($component->get('assistantMessages'))->toEqual([
        [
            'role' => 'user',
            'content' => 'Summarize the uploaded documents.',
        ],
        [
            'role' => 'assistant',
            'content' => 'There is one implementation report attached so far.',
        ],
    ]);

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) {
        expect((string) $prompt->agent->instructions())
            ->toContain('Active tab: Documents')
            ->toContain('Tab role: Document Intelligence Assistant')
            ->toContain('Implementation Progress Report')
            ->toContain('Tracks implementation delays across ministries.');

        return $prompt->contains('Summarize the uploaded documents.');
    });
});

test('workflow tab refuses finding generation before the llm call', function () {
    $review = plsReview([
        'title' => 'Workflow boundary review',
    ]);

    ReviewAssistantAgent::fake();

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])
        ->call('sendPrompt', 'Draft findings from this workflow stage.')
        ->assertSee('I can help with workflow stages and next steps in this tab, but I cannot generate findings, recommendations, or conclusions from the Workflow tab.');

    ReviewAssistantAgent::assertNeverPrompted();

    expect(DB::table('agent_conversations')->count())->toBe(1)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(2);
});

test('documents tab refuses unsupported claims about missing documents without support', function () {
    $review = plsReview([
        'title' => 'Sparse document review',
    ]);

    ReviewAssistantAgent::fake();

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'documents',
    ])
        ->call('sendPrompt', 'What missing regulations are definitely required here?')
        ->assertSee('I can only make claims about document contents or missing materials here when the current review has supporting uploads or approved reference sources.');

    ReviewAssistantAgent::assertNeverPrompted();
});

test('legislation tab refuses impact evaluation without evidence', function () {
    $review = plsReview([
        'title' => 'Legislation impact boundary review',
    ]);

    ReviewAssistantAgent::fake();

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'legislation',
    ])
        ->call('sendPrompt', 'Evaluate the impact of this law.')
        ->assertSee('I can explain the structure of the legislation in this tab, but I cannot evaluate impact or give final conclusions without supporting evidence.');

    ReviewAssistantAgent::assertNeverPrompted();
});

test('consultations tab refuses result analysis when no records exist', function () {
    $review = plsReview([
        'title' => 'Consultation boundary review',
    ]);

    ReviewAssistantAgent::fake();

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'consultations',
    ])
        ->call('sendPrompt', 'Analyze the consultation results.')
        ->assertSee('I can help design consultation activity in this tab, but I cannot analyze consultation results because no consultations or submissions are recorded yet.');

    ReviewAssistantAgent::assertNeverPrompted();
});

test('analysis tab instructions keep outputs provisional', function () {
    $review = plsReview([
        'title' => 'Analysis proviso review',
    ]);

    ReviewAssistantAgent::fake([
        'Potential themes are emerging, but they should still be treated as provisional.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'analysis',
    ])->call('sendPrompt', 'Group the current analysis into themes.');

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) {
        expect((string) $prompt->agent->instructions())
            ->toContain('Tab role: Analytical Support Assistant')
            ->toContain('Response style:')
            ->toContain('Keep findings and recommendation options explicitly provisional.');

        return $prompt->contains('Group the current analysis into themes.');
    });
});

test('the same review keeps one assistant conversation across tab changes for the same user', function () {
    $review = plsReview([
        'title' => 'Conversation continuity review',
    ]);

    ReviewAssistantAgent::fake(function (string $prompt) {
        return match (true) {
            str_contains($prompt, 'uploaded documents') => 'Documents response',
            default => 'Workflow response',
        };
    });

    $workflow = Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])
        ->call('sendPrompt', 'Summarize the current step.');

    $record = DB::table('agent_conversations')->first();
    $firstConversationId = $record->id;

    expect($record->pls_review_id)->toBe($review->id)
        ->and($record->user_id)->toBe(auth()->id())
        ->and($record->playbook_version)->toBe(app(PlaybookRepository::class)->versionForTab('workflow'));

    $documents = Livewire::test(AssistantSidebar::class, [
        'review' => $review->fresh(),
        'workspaceKey' => 'documents',
    ])
        ->assertSet('assistantConversationId', $firstConversationId)
        ->assertSee('Workflow response')
        ->call('sendPrompt', 'Summarize the uploaded documents.')
        ->assertSet('assistantConversationId', $firstConversationId);

    expect($documents->get('assistantMessages'))->toEqual([
        [
            'content' => 'Summarize the current step.',
            'role' => 'user',
        ],
        [
            'content' => 'Workflow response',
            'role' => 'assistant',
        ],
        [
            'content' => 'Summarize the uploaded documents.',
            'role' => 'user',
        ],
        [
            'content' => 'Documents response',
            'role' => 'assistant',
        ],
    ]);

    expect(DB::table('agent_conversations')->count())->toBe(1);

    $workflow->assertSet('assistantConversationId', $firstConversationId);
});

test('different reviews keep separate assistant conversations', function () {
    $firstReview = plsReview([
        'title' => 'First assistant conversation review',
    ]);
    $secondReview = plsReview([
        'title' => 'Second assistant conversation review',
    ]);

    ReviewAssistantAgent::fake([
        'First review response',
        'Second review response',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $firstReview,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'Summarize the current step.');

    Livewire::test(AssistantSidebar::class, [
        'review' => $secondReview,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'Summarize the current step.');

    $records = DB::table('agent_conversations')
        ->orderBy('pls_review_id')
        ->get();

    expect($records)->toHaveCount(2)
        ->and($records[0]->id)->not->toBe($records[1]->id);
});

test('assistant can show explicit uncertainty when the current record is insufficient', function () {
    $review = plsReview([
        'title' => 'Sparse documents review',
    ]);

    ReviewAssistantAgent::fake([
        'I do not have sufficient information to answer this from the current review record.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'documents',
    ])
        ->call('sendPrompt', 'Compare the current documents.')
        ->assertSee('I do not have sufficient information to answer this from the current review record.');
});

test('assistant responses are normalized before display', function () {
    $review = plsReview([
        'title' => 'Assistant formatting review',
    ]);

    ReviewAssistantAgent::fake([
        "**Status:** Pending\n\n**What this means in practice:**\n- Link the governing law.\n- Upload the briefing pack.",
    ]);

    $component = Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'Summarize the current step.');

    expect($component->get('assistantMessages'))->toEqual([
        [
            'role' => 'user',
            'content' => 'Summarize the current step.',
        ],
        [
            'role' => 'assistant',
            'content' => "Status: Pending\n\nWhat this means in practice:\n- Link the governing law.\n- Upload the briefing pack.",
        ],
    ]);

    $component
        ->assertSee('Status: Pending')
        ->assertSee('What this means in practice:')
        ->assertSee('Link the governing law.')
        ->assertSee('Upload the briefing pack.')
        ->assertDontSee('**Status:**')
        ->assertDontSee('**What this means in practice:**');
});

test('assistant shows an inline error state when the sdk call fails', function () {
    $review = plsReview([
        'title' => 'Assistant failure review',
    ]);

    ReviewAssistantAgent::fake(function () {
        throw new RuntimeException('AI provider unavailable');
    });

    $component = Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])
        ->call('sendPrompt', 'Summarize the current step.');

    expect($component->get('assistantMessages'))->toBe([
        [
            'role' => 'user',
            'content' => 'Summarize the current step.',
        ],
    ]);

    $component
        ->assertSet('assistantError', 'The assistant is unavailable right now. Check the AI provider configuration or try again.')
        ->assertSee('The assistant is unavailable right now. Check the AI provider configuration or try again.');
});

test('global reference docs are included for process questions', function () {
    $review = plsReview([
        'title' => 'Global reference review',
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'WFD Manual',
        'scope' => AssistantSourceScope::Global,
        'summary' => 'Use a documented workflow for post-legislative scrutiny and make each step explicit.',
        'content' => 'Use a documented workflow for post-legislative scrutiny and make each step explicit.',
    ]);

    ReviewAssistantAgent::fake([
        'According to the WFD manual, the workflow should stay explicit and documented.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'What does the WFD manual say about this workflow stage?');

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) {
        expect((string) $prompt->agent->instructions())
            ->toContain('Grounding priority: Jurisdiction guidance where available, then global reference guidance, then review-specific material only when directly relevant.')
            ->toContain('Global reference guidance: WFD Manual: Use a documented workflow for post-legislative scrutiny and make each step explicit.');

        return $prompt->contains('What does the WFD manual say about this workflow stage?');
    });
});

test('config reference docs remain a fallback when stored source documents are missing', function () {
    config()->set('pls_assistant.reference_documents.global', [
        [
            'label' => 'Fallback WFD Manual',
            'content' => 'Fallback guidance should only be used when stored source documents are unavailable.',
        ],
    ]);

    $review = plsReview([
        'title' => 'Fallback reference review',
    ]);

    ReviewAssistantAgent::fake([
        'The fallback guidance is available because there are no stored global source documents yet.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'What reference guidance is available here?');

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) {
        expect((string) $prompt->agent->instructions())
            ->toContain('Global reference guidance: Fallback WFD Manual: Fallback guidance should only be used when stored source documents are unavailable.');

        return $prompt->contains('What reference guidance is available here?');
    });
});

test('jurisdiction guidance is preferred for local practice questions', function () {
    $review = plsReview([
        'title' => 'Jurisdiction guidance review',
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'WFD Manual',
        'scope' => AssistantSourceScope::Global,
        'summary' => 'Government response tracking should be tied to the report record.',
        'content' => 'Government response tracking should be tied to the report record.',
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'Local Standing Orders',
        'scope' => AssistantSourceScope::Jurisdiction,
        'country_id' => $review->country_id,
        'jurisdiction_id' => $review->jurisdiction_id,
        'legislature_id' => $review->legislature_id,
        'summary' => 'In this jurisdiction, government responses are ordinarily logged against the report and follow-up calendar.',
        'content' => 'In this jurisdiction, government responses are ordinarily logged against the report and follow-up calendar.',
    ]);

    ReviewAssistantAgent::fake([
        'In this jurisdiction’s guidance, government responses are logged against the report and follow-up calendar.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'reports',
    ])->call('sendPrompt', 'What is the local practice for government responses in this parliament?');

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) {
        expect((string) $prompt->agent->instructions())
            ->toContain('Grounding priority: Jurisdiction guidance first, then global reference guidance, then review-specific material only if needed.')
            ->toContain('Jurisdiction guidance: Local Standing Orders: In this jurisdiction, government responses are ordinarily logged against the report and follow-up calendar.')
            ->toContain('Global reference guidance: WFD Manual: Government response tracking should be tied to the report record.');

        return $prompt->contains('What is the local practice for government responses in this parliament?');
    });
});

test('mixed grounding keeps global jurisdiction and review layers distinct', function () {
    $review = plsReview([
        'title' => 'Mixed grounding review',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Implementation Memo',
        'summary' => 'The current review record shows one ministry has delayed implementation by six months.',
    ]);

    DocumentChunk::factory()->create([
        'document_id' => $document->id,
        'chunk_index' => 0,
        'content' => 'The current review record shows one ministry has delayed implementation by six months.',
        'token_count' => 14,
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'WFD Manual',
        'scope' => AssistantSourceScope::Global,
        'summary' => 'Use post-legislative scrutiny to connect evidence, findings, and follow-up.',
        'content' => 'Use post-legislative scrutiny to connect evidence, findings, and follow-up.',
    ]);

    AssistantSourceDocument::factory()->create([
        'title' => 'Local PLS Guidance',
        'scope' => AssistantSourceScope::Jurisdiction,
        'jurisdiction_id' => $review->jurisdiction_id,
        'summary' => 'In this jurisdiction, implementation delays should be tied to agency accountability and reporting timelines.',
        'content' => 'In this jurisdiction, implementation delays should be tied to agency accountability and reporting timelines.',
    ]);

    ReviewAssistantAgent::fake([
        'According to the WFD manual, this should stay evidence-led. In this jurisdiction’s guidance, implementation delays should be tied to accountability timelines. In this review, the current record shows one ministry has delayed implementation by six months.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'analysis',
    ])->call('sendPrompt', 'Combine the process guidance, local practice, and current review evidence on implementation delays.');

    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt) {
        expect((string) $prompt->agent->instructions())
            ->toContain('Global reference guidance: WFD Manual: Use post-legislative scrutiny to connect evidence, findings, and follow-up.')
            ->toContain('Jurisdiction guidance: Local PLS Guidance: In this jurisdiction, implementation delays should be tied to agency accountability and reporting timelines.')
            ->toContain('Review record and documents: Implementation Memo: The current review record shows one ministry has delayed implementation by six months.');

        return $prompt->contains('Combine the process guidance, local practice, and current review evidence on implementation delays.');
    });
});
