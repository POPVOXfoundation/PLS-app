<?php

namespace App\Domain\Consultations;

use App\Domain\Consultations\Enums\ConsultationMaterialType;
use App\Domain\Documents\Document;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConsultationMaterial extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Consultations\ConsultationMaterialFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'consultation_id',
        'document_id',
        'stakeholder_id',
        'material_type',
    ];

    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'material_type' => ConsultationMaterialType::class,
        ];
    }
}
