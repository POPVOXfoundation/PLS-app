<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\StakeholdersPage;
use App\Models\User;
use App\Support\PlsAssistant\StakeholderSuggestionNormalizer;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
});

test('navigation questions receive useful source links without generating conclusions or an AI request', function () {
    $review = plsReview();
    ReviewAssistantAgent::fake();
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'workflow'])
        ->call('sendPrompt', 'Where can I generate a findings report?')
        ->assertSee('Use Generate report or Start a blank draft.')
        ->assertSeeHtml(route('pls.reviews.reports', $review));
    ReviewAssistantAgent::assertNeverPrompted();
});

test('assistant formatting preserves safe headings lists tables and links and keeps detail accessible', function () {
    $review = plsReview();
    ReviewAssistantAgent::fake(["Direct answer.\n\n## Details\n\n- First item\n- Second item\n\n| Source | State |\n| --- | --- |\n| [Review](https://example.com/source) | Missing results |\n\n<script>bad()</script>"]);
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'workflow'])->call('sendPrompt', 'What comes next?')
        ->assertSee('Direct answer.')->assertSee('Details, sources and limitations')
        ->assertSeeHtml('<h2>Details</h2>')->assertSeeHtml('<table>')->assertSeeHtml('href="https://example.com/source"')
        ->assertDontSeeHtml('<script>bad()</script>')->assertSee('Jump to latest answer');
});

test('explicit aliases group stakeholder sources while ambiguous aliases do not merge unrelated groups', function () {
    $normalizer = app(StakeholderSuggestionNormalizer::class);
    $suggestions = collect([
        ...$normalizer->normalize([['name' => 'International Federation of Women Lawyers (FIDA)', 'kind' => 'stakeholder', 'rationale' => 'First source']], 1, 'Bill'),
        ...$normalizer->normalize([['name' => 'FIDA', 'kind' => 'stakeholder', 'rationale' => 'Second source']], 2, 'Debate'),
    ]);
    $groups = $normalizer->group($suggestions);
    expect($groups)->toHaveCount(1)->and($groups[0]['sources'])->toHaveCount(2);
    $ambiguous = collect([
        ...$normalizer->normalize([['name' => 'Northern Rights Council (NRC)', 'kind' => 'stakeholder']], 1, 'A'),
        ...$normalizer->normalize([['name' => 'National Research Council (NRC)', 'kind' => 'stakeholder']], 2, 'B'),
        ...$normalizer->normalize([['name' => 'NRC', 'kind' => 'stakeholder']], 3, 'C'),
    ]);
    expect($normalizer->group($ambiguous))->toHaveCount(3);
});

test('dismissing a stakeholder group resolves every matching stored suggestion and keeps its source history', function () {
    $review = plsReview();
    $normalizer = app(StakeholderSuggestionNormalizer::class);
    foreach (['International Federation of Women Lawyers (FIDA)', 'FIDA'] as $index => $name) {
        $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText]);
        $suggestions = $normalizer->normalize([['name' => $name, 'kind' => 'stakeholder', 'rationale' => 'Source rationale '.$index]], $document->id, $document->title);
        $document->update(['metadata' => ['legislation_analysis' => ['status' => 'saved', 'stakeholder_suggestions' => $suggestions]]]);
        $firstId ??= $suggestions[0]['id'];
    }
    Livewire::test(StakeholdersPage::class, ['review' => $review])->call('dismissSuggestedStakeholder', $firstId)->assertHasNoErrors();
    foreach ($review->documents()->get() as $document) {
        expect(data_get($document->metadata, 'legislation_analysis.stakeholder_suggestions.0.status'))->toBe('dismissed');
        expect(data_get($document->metadata, 'legislation_analysis.stakeholder_suggestions.0.rationale'))->toContain('Source rationale');
    }
});

test('feedback assets are served locally rather than relying on external icon hosts', function () {
    foreach (['chat', 'close', 'bug', 'improvement', 'general', 'success'] as $name) {
        expect(file_exists(public_path('images/feedback/'.$name.'.svg')))->toBeTrue();
    }
    expect(config('volet.icon'))->toBe('/images/feedback/chat.svg');
});
