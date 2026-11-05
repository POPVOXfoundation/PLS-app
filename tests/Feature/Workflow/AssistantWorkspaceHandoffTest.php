<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\AnalysisPage;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

/**
 * Hand-off of an assistant reply to the Analysis or Reports workspace (review finding 2 on commit 4d2c598).
 *
 * Property 4: Bug Condition - Each workspace receives the reply from its own request.
 *
 * A reply that another tab stores in the same conversation between this send's locked write
 * and its hand-off must never be handed off instead of this send's own reply, and each
 * workspace keeps receiving the format it receives today.
 *
 * **Validates: Requirements 1.12, 2.16, 2.17, 3.14**
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

const HANDOFF_OTHER_TAB_REPLY = 'Other tab reply';

/**
 * The last statement of the locked write (persistConversation()), before the display reload.
 * The query grammar quotes identifiers with double quotes on both SQLite and PostgreSQL.
 */
const HANDOFF_LOCKED_WRITE_UPDATE_SQL = 'update "agent_conversations" set "playbook_version"';

const HANDOFF_ANALYSIS_REPLY = <<<'TEXT'
POTENTIAL FINDING:
Title: Reporting remains incomplete
Type: implementation gap
Draft: The available evidence suggests reporting is incomplete.
Supporting record: Annual report: "Reporting was delayed."
Limitations: Agency responses have not been recorded.
Reviewer question: Has the missing report now been published?
END FINDING
TEXT;

const HANDOFF_REPORT_REPLY = <<<'TEXT'
REPORT OUTLINE:
Report title: Draft report on implementation
SECTION:
Title: Purpose and scope
Purpose: Set out the legislation and questions under review.
Draw on: Review overview and linked legislation.
Limitations: Confirm the final scope with the review team.
END SECTION
END OUTLINE
TEXT;

const HANDOFF_ANALYSIS_REFUSAL = 'I can help with provisional findings, themes, and recommendation options in this tab, but I cannot present them as final conclusions.';

const HANDOFF_REPORTS_REFUSAL = 'I can help with report structure and provisional drafting here, but I cannot treat this as final publication language until the report record shows a published outcome.';

/**
 * Sends that hand off to the Analysis or Reports workspace, on the AI path and the refusal path.
 *
 * Refusal prompts are refused by ReviewAssistantRefusalGuard on a fresh review: 'final
 * recommendation' on analysis, and 'final report' on reports with no published report.
 *
 * @return array<string, array{workspaceKey: string, prompt: string, reply: string, expected: string, refused: bool, handoff: 'provisionalFindings'|'reportOutline'}>
 */
function handoffCases(): array
{
    return [
        'reports AI path' => [
            'workspaceKey' => 'reports',
            'prompt' => 'Prepare a provisional PLS report outline from the saved review record.',
            'reply' => HANDOFF_REPORT_REPLY,
            'expected' => HANDOFF_REPORT_REPLY,
            'refused' => false,
            'handoff' => 'reportOutline',
        ],
        'reports refusal path' => [
            'workspaceKey' => 'reports',
            'prompt' => 'Write the final report',
            'reply' => HANDOFF_REPORTS_REFUSAL,
            'expected' => HANDOFF_REPORTS_REFUSAL,
            'refused' => true,
            'handoff' => 'reportOutline',
        ],
        'analysis refusal path' => [
            'workspaceKey' => 'analysis',
            'prompt' => 'Give me the final recommendation',
            'reply' => HANDOFF_ANALYSIS_REFUSAL,
            'expected' => HANDOFF_ANALYSIS_REFUSAL,
            'refused' => true,
            'handoff' => 'provisionalFindings',
        ],
        'analysis AI path' => [
            'workspaceKey' => 'analysis',
            'prompt' => 'Suggest provisional findings from the saved review record.',
            'reply' => HANDOFF_ANALYSIS_REPLY,
            'expected' => HANDOFF_ANALYSIS_REPLY,
            'refused' => false,
            'handoff' => 'provisionalFindings',
        ],
    ];
}

/**
 * @return array<string, array{0: array{workspaceKey: string, prompt: string, reply: string, expected: string, refused: bool, handoff: 'provisionalFindings'|'reportOutline'}}>
 */
function handoffCaseDataset(string ...$names): array
{
    return collect(handoffCases())
        ->only($names === [] ? array_keys(handoffCases()) : $names)
        ->map(fn (array $case): array => [$case])
        ->all();
}

function handoffMountSidebar(PlsReview $review, string $workspaceKey): mixed
{
    return Livewire::test(AssistantSidebar::class, [
        'review' => $review->fresh(),
        'workspaceKey' => $workspaceKey,
    ]);
}

/**
 * Fake the agent so AI-path sends get the case's reply. Refusal-path sends never prompt it.
 *
 * @param  array{reply: string, refused: bool}  $case
 */
function handoffFakeAgent(array $case): void
{
    if ($case['refused']) {
        ReviewAssistantAgent::fake();

        return;
    }

    ReviewAssistantAgent::fake([$case['reply']]);
}

/**
 * Arm a one-shot listener that, right after the locked write's conversation update, stores an
 * assistant message from "another tab" in the same conversation. Its uuid7 id is a minute ahead,
 * so it sorts after this send's messages. The flag stops its own insert from re-triggering it.
 *
 * @return ArrayObject<string, bool>
 */
function handoffInterleaveOtherTabReply(): ArrayObject
{
    $state = new ArrayObject(['fired' => false]);

    DB::listen(function (QueryExecuted $query) use ($state): void {
        if ($state['fired'] || ! str_starts_with(strtolower($query->sql), HANDOFF_LOCKED_WRITE_UPDATE_SQL)) {
            return;
        }

        $state['fired'] = true;

        $conversation = DB::table('agent_conversations')
            ->where('user_id', auth()->id())
            ->orderByDesc('updated_at')
            ->first();

        DB::table('agent_conversation_messages')->insert([
            'id' => (string) Str::uuid7(now()->addMinute()),
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'agent' => ReviewAssistantAgent::class,
            'role' => 'assistant',
            'content' => HANDOFF_OTHER_TAB_REPLY,
            'attachments' => '[]',
            'tool_calls' => '[]',
            'tool_results' => '[]',
            'usage' => '[]',
            'meta' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    return $state;
}

/**
 * @param  array{prompt: string, handoff: 'provisionalFindings'|'reportOutline'}  $case
 */
function handoffSend(mixed $sidebar, array $case): mixed
{
    return $sidebar->call(
        'sendRequestedPrompt',
        prompt: $case['prompt'],
        provisionalFindings: $case['handoff'] === 'provisionalFindings',
        reportOutline: $case['handoff'] === 'reportOutline',
    );
}

/**
 * Assert the workspace's *-generated event was dispatched and return its content.
 *
 * @param  array{handoff: 'provisionalFindings'|'reportOutline'}  $case
 */
function handoffDeliveredContent(mixed $sidebar, array $case): mixed
{
    $deliveredContent = null;
    $capture = function (string $name, array $params) use (&$deliveredContent): bool {
        $deliveredContent = $params['content'] ?? null;

        return true;
    };

    if ($case['handoff'] === 'provisionalFindings') {
        $sidebar
            ->assertDispatchedTo(AnalysisPage::class, 'provisional-findings-generated', $capture)
            ->assertNotDispatched('provisional-findings-failed');
    } else {
        $sidebar
            ->assertDispatchedTo(ReportsPage::class, 'report-outline-generated', $capture)
            ->assertNotDispatched('report-outline-failed');
    }

    return $deliveredContent;
}

test('a reply stored by another tab during the send is not handed off instead of this send\'s reply', function (array $case) {
    handoffFakeAgent($case);

    $review = plsReview(['title' => 'Interleaved hand-off: '.$case['prompt']]);
    $sidebar = handoffMountSidebar($review, $case['workspaceKey']);

    $listener = handoffInterleaveOtherTabReply();

    handoffSend($sidebar, $case)->assertSet('assistantError', null);

    expect($listener['fired'])->toBeTrue()
        ->and(DB::table('agent_conversation_messages')->where('content', HANDOFF_OTHER_TAB_REPLY)->count())->toBe(1)
        ->and(DB::table('agent_conversation_messages')->where('role', 'assistant')->orderByDesc('id')->value('content'))->toBe(HANDOFF_OTHER_TAB_REPLY);

    $deliveredContent = handoffDeliveredContent($sidebar, $case);

    expect($deliveredContent)->toBe($case['expected'])
        ->and($deliveredContent)->not->toContain(HANDOFF_OTHER_TAB_REPLY);
})->with(handoffCaseDataset());

test('the reports workspace receives the original markdown reply with headings preserved', function () {
    $case = [
        ...handoffCases()['reports AI path'],
        'reply' => "## Outline\n\n**Introduction**\n1. Scope\n* Method\n\n\n\nEnd",
    ];
    handoffFakeAgent($case);

    $sidebar = handoffMountSidebar(plsReview(['title' => 'Reports markdown hand-off']), 'reports');

    handoffSend($sidebar, $case)->assertSet('assistantError', null);

    expect(handoffDeliveredContent($sidebar, $case))->toBe($case['reply']);
});

test('the analysis workspace receives the raw potential finding reply', function () {
    $case = handoffCases()['analysis AI path'];
    handoffFakeAgent($case);

    $sidebar = handoffMountSidebar(plsReview(['title' => 'Analysis raw hand-off']), 'analysis');

    handoffSend($sidebar, $case)->assertSet('assistantError', null);

    expect(handoffDeliveredContent($sidebar, $case))->toBe(HANDOFF_ANALYSIS_REPLY);
});

test('refusal paths hand off the refusal sentence unchanged', function (array $case) {
    handoffFakeAgent($case);

    $sidebar = handoffMountSidebar(plsReview(['title' => 'Refusal hand-off: '.$case['prompt']]), $case['workspaceKey']);

    handoffSend($sidebar, $case)->assertSet('assistantError', null);

    expect(handoffDeliveredContent($sidebar, $case))->toBe($case['reply']);

    ReviewAssistantAgent::assertNeverPrompted();
})->with(handoffCaseDataset('analysis refusal path', 'reports refusal path'));

test('a whitespace-only reply dispatches the workspace failure event and no generated event', function (array $case) {
    $case = [...$case, 'reply' => "  \n\t  \n"];
    handoffFakeAgent($case);

    $sidebar = handoffMountSidebar(plsReview(['title' => 'Empty hand-off: '.$case['prompt']]), $case['workspaceKey']);

    handoffSend($sidebar, $case);

    if ($case['handoff'] === 'provisionalFindings') {
        $sidebar
            ->assertDispatchedTo(AnalysisPage::class, 'provisional-findings-failed')
            ->assertNotDispatched('provisional-findings-generated');
    } else {
        $sidebar
            ->assertDispatchedTo(ReportsPage::class, 'report-outline-failed')
            ->assertNotDispatched('report-outline-generated');
    }
})->with(handoffCaseDataset('reports AI path', 'analysis AI path'));
