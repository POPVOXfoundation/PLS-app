<?php

use App\Models\User;

test('admin sees the assistant playbooks link in the top user menu', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Assistant playbooks');
});

test('non-admin users do not see the assistant playbooks link in the top user menu', function () {
    $reviewer = User::factory()->reviewer()->create();

    $this->actingAs($reviewer)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Assistant playbooks');
});
