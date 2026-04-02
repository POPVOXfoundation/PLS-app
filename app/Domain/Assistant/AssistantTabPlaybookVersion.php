<?php

namespace App\Domain\Assistant;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssistantTabPlaybookVersion extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\Assistant\AssistantTabPlaybookVersionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'assistant_tab_playbook_id',
        'version_number',
        'role',
        'intro',
        'objectives',
        'allowed_capabilities',
        'disallowed_capabilities',
        'suggested_prompts',
        'rules',
        'guardrails',
        'response_style',
        'change_note',
        'created_by',
    ];

    public function playbook(): BelongsTo
    {
        return $this->belongsTo(AssistantTabPlaybook::class, 'assistant_tab_playbook_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'objectives' => 'array',
            'allowed_capabilities' => 'array',
            'disallowed_capabilities' => 'array',
            'suggested_prompts' => 'array',
            'rules' => 'array',
            'guardrails' => 'array',
            'response_style' => 'array',
        ];
    }
}
