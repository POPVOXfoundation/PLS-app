<?php

namespace App\Domain\Documents;

use App\Domain\Analysis\EvidenceItem;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\ConsultationMaterial;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use Database\Factories\Domain\Documents\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'pls_review_id',
        'title',
        'document_type',
        'storage_path',
        'mime_type',
        'file_size',
        'summary',
        'metadata',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PlsReview::class, 'pls_review_id');
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }

    public function legislation(): BelongsToMany
    {
        return $this->belongsToMany(
            Legislation::class,
            'pls_review_legislation',
            'pls_review_id',
            'legislation_id',
            'pls_review_id',
            'id',
        )->withPivot('relationship_type')->withTimestamps();
    }

    public function sourcedLegislation(): HasMany
    {
        return $this->hasMany(Legislation::class, 'source_document_id');
    }

    public function evidenceItems(): HasMany
    {
        return $this->hasMany(EvidenceItem::class);
    }

    public function consultations(): HasMany
    {
        return $this->hasMany(Consultation::class);
    }

    public function consultationMaterials(): HasMany
    {
        return $this->hasMany(ConsultationMaterial::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    public function governmentResponses(): HasMany
    {
        return $this->hasMany(GovernmentResponse::class);
    }

    public function extractedText(): string
    {
        return trim($this->chunks->sortBy('chunk_index')->pluck('content')->implode("\n\n"));
    }

    public function reviewWarnings(): array
    {
        return array_values(array_unique(array_merge(
            (array) data_get($this->metadata, 'document_analysis.warnings', []),
            (array) data_get($this->metadata, 'legislation_analysis.warnings', []),
        )));
    }

    public function isReportingTemplate(): bool
    {
        return $this->document_type === DocumentType::ReportingTemplate
            || data_get($this->metadata, 'purpose') === 'report_template'
            || (bool) preg_match('/blank.{0,35}template|template.{0,65}(?:without filled|no (?:filled|completed|reported) data)/i', implode(' ', $this->reviewWarnings()));
    }

    public function evidenceRoleLabel(): string
    {
        return $this->isReportingTemplate()
            ? __('Template — no reported results')
            : Str::headline($this->document_type->value);
    }

    public function excludedFromAnalysis(): bool
    {
        return (bool) data_get($this->metadata, 'human_review.excluded', false);
    }

    public function reviewStatusLabel(): string
    {
        return $this->excludedFromAnalysis() ? __('Excluded from analysis')
            : (data_get($this->metadata, 'human_review.reviewed_at') ? __('Checked by team') : __('Not checked'));
    }

    public function processingStatus(): string
    {
        $status = data_get($this->metadata, 'extraction.status');
        $queued = data_get($this->metadata, 'extraction.queued_at');
        if ($status === 'failed' || (in_array($status, ['queued', 'processing']) && $queued && Carbon::parse($queued)->lt(now()->subMinutes(30)))) {
            return 'needs_attention';
        }
        if ($status === 'queued') {
            return 'queued';
        }
        if ($status === 'processing') {
            return 'processing';
        }
        if ($this->relationLoaded('chunks') ? $this->chunks->isNotEmpty() : (isset($this->chunks_count) ? $this->chunks_count > 0 : $this->chunks()->exists())) {
            return 'ready';
        }

        return 'uploaded';
    }

    public function processingLabel(): string
    {
        return match ($this->processingStatus()) {
            'queued' => __('Queued'), 'processing' => __('Reading text / preparing record'),
            'ready' => __('Text ready'), 'needs_attention' => __('Needs attention'),
            default => filled($this->storage_path) ? __('Uploaded — text not yet available') : __('Reference only — full text needed'),
        };
    }

    public function originalUrl(): ?string
    {
        if (filled($this->storage_path)) {
            return route('pls.reviews.documents.original', [$this->pls_review_id, $this->id]);
        }
        $url = data_get($this->metadata, 'source_url');

        return is_string($url) && filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https']) ? $url : null;
    }

    public function fileSizeLabel(): string
    {
        if ($this->file_size === null) {
            return __('Unknown size');
        }

        if ($this->file_size >= 1_048_576) {
            return number_format($this->file_size / 1_048_576, 1).' MB';
        }

        if ($this->file_size >= 1024) {
            return number_format($this->file_size / 1024, 1).' KB';
        }

        return number_format($this->file_size).' B';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'metadata' => 'array',
        ];
    }
}
