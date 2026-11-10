<?php

namespace App\Domain\Documents;

use App\Domain\Analysis\EvidenceItem;
use App\Domain\Analysis\FindingCitation;
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

    public function findingCitations(): HasMany
    {
        return $this->hasMany(FindingCitation::class);
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

    public function isReportTemplate(): bool
    {
        return $this->document_type === DocumentType::ReportingTemplate || data_get($this->metadata, 'purpose') === 'report_template';
    }

    public function roleLabel(): string
    {
        return $this->isReportTemplate() ? __('Reporting template — structure only') : Str::headline($this->document_type->value);
    }

    public function teamCheckLabel(): string
    {
        $checked = data_get($this->metadata, 'source_review.checked_text_hash');
        if ($checked === null) {
            return __('Not team checked');
        }
        $current = data_get($this->metadata, 'extraction.text_sha256') ?? hash('sha256', $this->extractedText());

        return hash_equals($checked, $current) ? __('Team check recorded') : __('Team check needs updating: text changed');
    }

    public function originalUrl(): ?string
    {
        if (filled($this->storage_path)) {
            return route('pls.reviews.documents.original', [$this->pls_review_id, $this]);
        }
        $url = (string) data_get($this->metadata, 'source_url', '');

        return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true) ? $url : null;
    }

    public function processingLabel(): string
    {
        $status = data_get($this->metadata, 'extraction.status');
        if (in_array($status, ['queued', 'processing'], true)) {
            return $status === 'queued' ? __('Queued — waiting for worker') : __('Processing');
        }
        if ($status === 'failed') {
            return __('Needs attention');
        }
        if (($this->chunks_count ?? $this->chunks()->count()) > 0) {
            return __('Text ready — not verified');
        }

        return blank($this->storage_path) ? __('Reference only — text missing') : __('Uploaded — text missing');
    }

    public function extractedText(): string
    {
        if ($this->relationLoaded('chunks')) {
            return $this->chunks->sortBy('chunk_index')->pluck('content')->implode("\n\n");
        }

        return $this->chunks()->orderBy('chunk_index')->pluck('content')->implode("\n\n");
    }

    public function pageCoverageLabel(): string
    {
        $total = (int) data_get($this->metadata, 'extraction.page_coverage.total_pages', 0);
        if ($total === 0) {
            return __('Page coverage unavailable. Extracted text has not been checked against every original page.');
        }
        $pages = count((array) data_get($this->metadata, 'extraction.page_coverage.pages_with_text', []));

        $partial = data_get($this->metadata, 'extraction.page_coverage.job_status') === 'PARTIAL_SUCCESS' ? __('Extraction reported partial success.').' ' : '';

        return $partial.__('Text returned on :read of :total reported pages. This does not verify OCR completeness; compare with the original.', ['read' => $pages, 'total' => $total]);
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
