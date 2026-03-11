<?php

namespace App\Domain\Consultations;

use App\Domain\Documents\Document;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Stakeholder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Consultations\SubmissionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'stakeholder_id',
        'document_id',
        'submitted_at',
        'summary',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function stakeholder(): BelongsTo
    {
        return $this->belongsTo(Stakeholder::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }
}
