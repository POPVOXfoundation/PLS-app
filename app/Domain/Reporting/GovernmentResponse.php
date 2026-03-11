<?php

namespace App\Domain\Reporting;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class GovernmentResponse extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reporting\GovernmentResponseFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'report_id',
        'document_id',
        'response_status',
        'received_at',
        'summary',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
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
            'response_status' => GovernmentResponseStatus::class,
            'received_at' => 'datetime',
        ];
    }
}
