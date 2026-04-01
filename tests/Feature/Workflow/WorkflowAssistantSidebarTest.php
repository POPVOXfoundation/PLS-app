<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Models\User;
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
        'Analytical Support Assistant',
        [
            'Summarize the current report status.',
            'Explain the current government response status.',
            'Suggest a report structure from the current record.',
        ],
    ],
]);

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

test('assistant can reset the saved conversation for the current review', function () {
    $review = plsReview([
        'title' => 'Resettable assistant conversation',
    ]);

    ReviewAssistantAgent::fake([
        'Workflow response',
    ]);

    $component = Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])->call('sendPrompt', 'Summarize the current step.');

    $conversationId = DB::table('agent_conversations')->value('id');

    expect($conversationId)->not->toBeNull()
        ->and(DB::table('agent_conversation_messages')->where('conversation_id', $conversationId)->count())->toBe(2);

    $component->call('resetAssistantConversation')
        ->assertSet('assistantConversationId', null)
        ->assertSet('assistantMessages', [])
        ->assertSet('assistantInput', '')
        ->assertSet('assistantError', null)
        ->assertSee('How can I help?');

    expect(DB::table('agent_conversations')->count())->toBe(0)
        ->and(DB::table('agent_conversation_messages')->count())->toBe(0);
});

test('workflow prompts compile current step progress and guidance into the agent instructions', function () {
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
        ->and($record->playbook_version)->toBe(config('pls_assistant.version'));

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

test('assistant can show a scoped refusal returned for an out of scope request', function () {
    $review = plsReview([
        'title' => 'Workflow boundary review',
    ]);

    ReviewAssistantAgent::fake([
        'I can help with workflow stages and next steps in this tab, but I cannot draft final recommendations from the Workflow tab.',
    ]);

    Livewire::test(AssistantSidebar::class, [
        'review' => $review,
        'workspaceKey' => 'workflow',
    ])
        ->call('sendPrompt', 'Draft a final recommendation from the current review.')
        ->assertSee('I can help with workflow stages and next steps in this tab, but I cannot draft final recommendations from the Workflow tab.');
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
