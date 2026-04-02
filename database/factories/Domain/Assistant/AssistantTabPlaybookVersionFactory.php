<?php

namespace Database\Factories\Domain\Assistant;

use App\Domain\Assistant\AssistantTabPlaybook;
use App\Domain\Assistant\AssistantTabPlaybookVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssistantTabPlaybookVersion>
 */
class AssistantTabPlaybookVersionFactory extends Factory
{
    protected $model = AssistantTabPlaybookVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assistant_tab_playbook_id' => AssistantTabPlaybook::factory(),
            'version_number' => 1,
            'role' => fake()->jobTitle(),
            'intro' => fake()->sentence(),
            'objectives' => [fake()->sentence(), fake()->sentence()],
            'allowed_capabilities' => [fake()->sentence(3), fake()->sentence(3)],
            'disallowed_capabilities' => [],
            'suggested_prompts' => [fake()->sentence(4), fake()->sentence(4)],
            'rules' => [fake()->sentence(), fake()->sentence()],
            'guardrails' => [fake()->sentence()],
            'response_style' => [fake()->sentence()],
            'change_note' => fake()->sentence(),
            'created_by' => null,
        ];
    }
}
