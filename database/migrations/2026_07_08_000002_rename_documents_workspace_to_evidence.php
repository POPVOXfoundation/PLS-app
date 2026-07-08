<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assistant_tab_playbooks')
            ->where('tab_key', 'documents')
            ->update(['label' => 'Evidence']);

        DB::table('assistant_tab_playbook_versions')
            ->join('assistant_tab_playbooks', 'assistant_tab_playbooks.id', '=', 'assistant_tab_playbook_versions.assistant_tab_playbook_id')
            ->where('assistant_tab_playbooks.tab_key', 'documents')
            ->update([
                'assistant_tab_playbook_versions.role' => 'Evidence Intelligence Assistant',
                'assistant_tab_playbook_versions.intro' => "You're in Evidence. I can help you review what you've uploaded, identify missing materials, and understand what each evidence source contains.",
            ]);
    }

    public function down(): void
    {
        DB::table('assistant_tab_playbooks')
            ->where('tab_key', 'documents')
            ->update(['label' => 'Documents']);

        DB::table('assistant_tab_playbook_versions')
            ->join('assistant_tab_playbooks', 'assistant_tab_playbooks.id', '=', 'assistant_tab_playbook_versions.assistant_tab_playbook_id')
            ->where('assistant_tab_playbooks.tab_key', 'documents')
            ->update([
                'assistant_tab_playbook_versions.role' => 'Document Intelligence Assistant',
                'assistant_tab_playbook_versions.intro' => "You're in Documents. I can help you review what you've uploaded, identify missing materials, and understand what each document contains.",
            ]);
    }
};
