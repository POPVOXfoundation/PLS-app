<?php

namespace App\Domain\Analysis;

use App\Domain\Documents\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingCitation extends Model
{
    protected $fillable = ['finding_id', 'document_id', 'passage', 'passage_hash', 'relationship', 'note', 'finding_fingerprint', 'source_fingerprint', 'reviewed_by'];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function isCurrent(): bool
    {
        return $this->finding !== null && $this->document !== null
            && $this->finding_fingerprint === $this->finding->supportFingerprint()
            && $this->source_fingerprint === hash('sha256', $this->document->extractedText());
    }
}
