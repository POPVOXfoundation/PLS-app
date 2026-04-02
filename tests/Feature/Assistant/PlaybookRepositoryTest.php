<?php

use App\Domain\Assistant\Actions\ImportAssistantTabPlaybooksFromDocs;
use App\Domain\Assistant\AssistantTabPlaybook;
use App\Domain\Assistant\AssistantTabPlaybookVersion;
use App\Models\User;
use App\Support\PlsAssistant\PlaybookRepository;
use App\Support\PlsAssistant\ReviewAssistantContextBuilder;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
});

test('repository returns the active db-backed playbook for a tab', function () {
    $playbook = AssistantTabPlaybook::query()->where('tab_key', 'workflow')->firstOrFail();

    $version = AssistantTabPlaybookVersion::factory()
        ->for($playbook, 'playbook')
        ->create([
            'version_number' => $playbook->nextVersionNumber(),
            'role' => 'Workflow Database Override',
            'intro' => 'This workflow tab is being served from the database.',
            'suggested_prompts' => [
                'Use the database-backed workflow prompts.',
            ],
            'created_by' => auth()->id(),
            'change_note' => 'Promote DB-backed workflow copy.',
        ]);

    $playbook->forceFill([
        'active_version_id' => $version->id,
    ])->save();

    $repository = app(PlaybookRepository::class);

    expect($repository->tab('workflow'))
        ->toMatchArray([
            'role' => 'Workflow Database Override',
            'intro' => 'This workflow tab is being served from the database.',
            'suggested_prompts' => [
                'Use the database-backed workflow prompts.',
            ],
        ])
        ->and($repository->versionForTab('workflow'))->toBe('db:v2:workflow');
});

test('repository falls back to config when a tab has no db playbook record', function () {
    AssistantTabPlaybook::query()
        ->where('tab_key', 'workflow')
        ->delete();

    $repository = app(PlaybookRepository::class);

    expect($repository->tab('workflow'))
        ->toMatchArray([
            'role' => config('pls_assistant.tabs.workflow.role'),
            'intro' => config('pls_assistant.tabs.workflow.intro'),
        ])
        ->and($repository->versionForTab('workflow'))
        ->toStartWith(config('pls_assistant.version').':workflow:');
});

test('repository keeps global system rules in config', function () {
    expect(app(PlaybookRepository::class)->systemRules())
        ->toBe(config('pls_assistant.system_rules'));
});

test('unknown tabs still resolve to workflow', function () {
    $repository = app(PlaybookRepository::class);

    expect($repository->resolveWorkspaceKey('not-a-real-tab'))->toBe('workflow')
        ->and($repository->workspaceLabel('not-a-real-tab'))->toBe('Workflow')
        ->and($repository->tab('not-a-real-tab')['role'])->toBe($repository->tab('workflow')['role']);
});

test('context builder uses the active db playbook and changes version metadata when the active version changes', function () {
    $review = plsReview([
        'title' => 'Database-backed playbook review',
    ]);

    $playbook = AssistantTabPlaybook::query()->where('tab_key', 'workflow')->firstOrFail();

    $builder = app(ReviewAssistantContextBuilder::class);
    $originalContext = $builder->build($review, 'workflow');

    $version = AssistantTabPlaybookVersion::factory()
        ->for($playbook, 'playbook')
        ->create([
            'version_number' => $playbook->nextVersionNumber(),
            'role' => 'Workflow Context Builder Override',
            'intro' => 'This intro should now come from the active database version.',
            'created_by' => auth()->id(),
            'change_note' => 'Switch workflow context builder copy to DB version 2.',
        ]);

    $playbook->forceFill([
        'active_version_id' => $version->id,
    ])->save();

    $updatedContext = app(ReviewAssistantContextBuilder::class)->build($review->fresh(), 'workflow');

    expect($originalContext['playbook_version'])->toBe('db:v1:workflow')
        ->and($updatedContext['playbook']['role'])->toBe('Workflow Context Builder Override')
        ->and($updatedContext['intro'])->toContain('This intro should now come from the active database version.')
        ->and($updatedContext['playbook_version'])->toBe('db:v2:workflow')
        ->and($updatedContext['context'])->toContain('Active tab: Workflow');
});

test('compiled playbook seed data can be imported without the source docs being present', function () {
    $docsPath = base_path('.codex/PLS Docs');
    $hiddenDocsPath = base_path('.codex/PLS Docs.__tmp_for_test');

    if (is_dir($hiddenDocsPath)) {
        rename($hiddenDocsPath, $docsPath);
    }

    expect(is_dir($docsPath))->toBeTrue();

    rename($docsPath, $hiddenDocsPath);

    try {
        AssistantTabPlaybookVersion::query()->delete();
        AssistantTabPlaybook::query()->delete();

        app(ImportAssistantTabPlaybooksFromDocs::class)->handle();

        $playbook = AssistantTabPlaybook::query()
            ->with('activeVersion')
            ->where('tab_key', 'workflow')
            ->firstOrFail();

        expect($playbook->label)->toBe('Workflow')
            ->and($playbook->activeVersion)->not->toBeNull()
            ->and($playbook->activeVersion->role)->toBe('Process Guide')
            ->and($playbook->activeVersion->intro)->toContain("You're in Workflow.");
    } finally {
        rename($hiddenDocsPath, $docsPath);
    }
});
