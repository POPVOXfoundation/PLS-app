<?php

namespace App\Domain\Reporting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportVersion extends Model
{
    protected $fillable = ['report_id', 'revision', 'title', 'content', 'status', 'created_by'];

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }
}
