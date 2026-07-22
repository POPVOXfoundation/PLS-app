<?php

namespace Database\Factories\Domain\Consultations;

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\ConsultationMaterial;
use App\Domain\Consultations\Enums\ConsultationMaterialType;
use App\Domain\Documents\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationMaterial>
 */
class ConsultationMaterialFactory extends Factory
{
    protected $model = ConsultationMaterial::class;

    public function definition(): array
    {
        return [
            'consultation_id' => Consultation::factory(),
            'document_id' => Document::factory(),
            'stakeholder_id' => null,
            'material_type' => fake()->randomElement(ConsultationMaterialType::cases()),
        ];
    }
}
