<?php

namespace App\Domain\Reporting;

use App\Domain\Documents\Document;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reviews\PlsReview;
use Database\Factories\Domain\Reporting\ReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
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
        'content',
        'content_versions',
        'generation_status', 'generation_token', 'generation_template_id', 'generation_error', 'generation_started_at', 'revision',
    ];

    protected static function booted(): void
    {
        static::creating(fn (Report $report) => $report->generation_token = (string) Str::uuid());
        static::updating(function (Report $report): void {
            if ($report->isDirty(['content', 'title'])) {
                $report->revision = ((int) $report->getOriginal('revision')) + 1;
            }
            if ($report->isDirty(['content', 'title']) && filled($report->getOriginal('content'))) {
                $report->content_versions = [...($report->getOriginal('content_versions') ?? []), [
                    'title' => $report->getOriginal('title'),
                    'content' => $report->getOriginal('content'),
                    'saved_at' => $report->getOriginal('updated_at')?->toIso8601String(),
                ]];
            }
        });
    }

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
            'revision' => 'integer',
            'generation_started_at' => 'datetime',
            'content_versions' => 'array',
            'report_type' => ReportType::class,
            'status' => ReportStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
