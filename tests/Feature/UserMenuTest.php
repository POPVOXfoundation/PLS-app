<?php

use App\Models\User;

test('admin sees the assistant playbooks link in the top user menu', function () {
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Assistant playbooks');

    expect(substr_count($response->getContent(), 'Assistant playbooks'))->toBe(1);
});

test('non-admin users do not see the assistant playbooks link in the top user menu', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Assistant playbooks');
});
