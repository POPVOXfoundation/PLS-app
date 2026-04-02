<?php

namespace Database\Factories\Domain\Assistant;

use App\Domain\Assistant\AssistantTabPlaybook;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssistantTabPlaybook>
 */
class AssistantTabPlaybookFactory extends Factory
{
    protected $model = AssistantTabPlaybook::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tabKey = fake()->unique()->slug(2);

        return [
            'tab_key' => $tabKey,
            'label' => Str::headline($tabKey),
            'active_version_id' => null,
        ];
    }
}
