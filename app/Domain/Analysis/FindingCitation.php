<?php

namespace App\Domain\Analysis;

use App\Domain\Documents\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FindingCitation extends Model
{
    protected $fillable = ['finding_id', 'document_id', 'passage', 'relationship', 'note', 'finding_fingerprint', 'reviewed_by'];

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class);
    }
}
