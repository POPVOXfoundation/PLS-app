<?php

namespace App\Domain\Reporting;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Reporting\ReportFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'title',
        'report_type',
        'status',
        'document_id',
        'published_at',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function governmentResponses(): HasMany
    {
        return $this->hasMany(GovernmentResponse::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'status' => ReportStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
