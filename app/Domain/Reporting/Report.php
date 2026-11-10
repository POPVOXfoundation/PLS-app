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

class Report extends Model
{
    /** @use HasFactory<ReportFactory> */
    use HasFactory;

    protected $attributes = ['revision' => 0];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'title',
        'content',
        'revision',
        'generation_status', 'generation_token', 'generation_error', 'generation_requested_at', 'generation_template_id',
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

    public function versions(): HasMany
    {
        return $this->hasMany(ReportVersion::class)->orderByDesc('revision');
    }

    public function checkpoint(): void
    {
        $this->versions()->firstOrCreate(['revision' => $this->revision], [
            'title' => $this->title, 'content' => $this->content, 'status' => $this->status->value, 'created_by' => auth()->id(),
        ]);
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
            'generation_requested_at' => 'datetime',
            'report_type' => ReportType::class,
            'status' => ReportStatus::class,
            'published_at' => 'datetime',
        ];
    }
}
