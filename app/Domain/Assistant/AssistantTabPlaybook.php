<?php

namespace App\Domain\Assistant;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssistantTabPlaybook extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Assistant\AssistantTabPlaybookFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'tab_key',
        'label',
        'active_version_id',
    ];

    public function activeVersion(): BelongsTo
    {
        return $this->belongsTo(AssistantTabPlaybookVersion::class, 'active_version_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(AssistantTabPlaybookVersion::class)->orderByDesc('version_number');
    }

    public function nextVersionNumber(): int
    {
        return (int) $this->versions()->max('version_number') + 1;
    }
}
