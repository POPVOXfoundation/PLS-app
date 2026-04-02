<?php

use App\Domain\Assistant\AssistantTabPlaybook;
use App\Domain\Assistant\AssistantTabPlaybookVersion;
use App\Livewire\Pls\Assistant\TabPlaybooks;
use App\Models\User;
use App\Support\PlsAssistant\PlaybookRepository;
use Livewire\Livewire;

test('admin can access the assistant playbook management page', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('pls.assistant.playbooks'))
        ->assertSuccessful()
        ->assertSee('Assistant playbooks')
        ->assertSee('Workflow');
});

test('non-admin users cannot access the assistant playbook management page', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer)
        ->get(route('pls.assistant.playbooks'))
        ->assertForbidden();

    Livewire::actingAs($reviewer)
        ->test(TabPlaybooks::class)
        ->assertForbidden();
});

test('admin can save a new structured playbook version and make it active', function () {
    $admin = User::factory()->admin()->create();
    $playbook = AssistantTabPlaybook::query()->where('tab_key', 'workflow')->firstOrFail();

    Livewire::actingAs($admin)
        ->test(TabPlaybooks::class)
        ->set('role', 'Workflow Governance Assistant')
        ->set('intro', 'Use the active workflow record to guide the next process step.')
        ->set('objectivesText', "Summarize the current step\nRecommend the next action")
        ->set('allowedCapabilitiesText', "Explain workflow stages\nDraft workflow updates")
        ->set('disallowedCapabilitiesText', 'Make final policy decisions')
        ->set('suggestedPromptsText', "Summarize the workflow status\nDraft a short next-step note")
        ->set('rulesText', "Stay inside workflow scope\nUse the current step as the primary frame")
        ->set('guardrailsText', 'Do not generate findings from the workflow tab')
        ->set('responseStyleText', "Keep answers concise\nPrefer short checklists when useful")
        ->set('changeNote', 'Refine workflow governance wording.')
        ->call('saveVersion')
        ->assertHasNoErrors();

    $playbook->refresh()->load('activeVersion');

    expect($playbook->activeVersion)->not->toBeNull()
        ->and($playbook->activeVersion->version_number)->toBe(2)
        ->and($playbook->activeVersion->role)->toBe('Workflow Governance Assistant')
        ->and($playbook->activeVersion->disallowed_capabilities)->toBe([
            'Make final policy decisions',
        ])
        ->and(app(PlaybookRepository::class)->tab('workflow')['role'])->toBe('Workflow Governance Assistant')
        ->and(app(PlaybookRepository::class)->versionForTab('workflow'))->toBe('db:v2:workflow');
});

test('admin can reactivate an older version', function () {
    $admin = User::factory()->admin()->create();
    $playbook = AssistantTabPlaybook::query()->where('tab_key', 'workflow')->firstOrFail();
    $firstVersionId = $playbook->versions()->where('version_number', 1)->value('id');

    $secondVersion = AssistantTabPlaybookVersion::factory()
        ->for($playbook, 'playbook')
        ->create([
            'version_number' => $playbook->nextVersionNumber(),
            'role' => 'Workflow Version Two',
            'created_by' => $admin->id,
            'change_note' => 'Promote workflow version two.',
        ]);

    $playbook->forceFill([
        'active_version_id' => $secondVersion->id,
    ])->save();

    Livewire::actingAs($admin)
        ->test(TabPlaybooks::class)
        ->call('activateVersion', $firstVersionId)
        ->assertHasNoErrors();

    $playbook->refresh();

    expect($playbook->active_version_id)->toBe($firstVersionId)
        ->and(app(PlaybookRepository::class)->versionForTab('workflow'))->toBe('db:v1:workflow')
        ->and(app(PlaybookRepository::class)->tab('workflow')['role'])->toBe('Process Guide');
});
