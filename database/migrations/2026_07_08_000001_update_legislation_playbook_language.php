<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->updateActiveLegislationPlaybook([
            "You're in Legislation. I can help you understand the structure of the law under review, identify its implementation obligations, and flag where secondary legislation may be required." => "You're in Legislation. I can help you understand the structure of the legislation under review, identify its implementation obligations, and flag where secondary legislation may be required.",
            'Explain the structure, key provisions, and internal architecture of the law under review.' => 'Explain the structure, key provisions, and internal architecture of the legislation under review.',
            'Flag where understanding a provision depends on missing cross-referenced laws or secondary legislation.' => 'Flag where understanding a provision depends on missing cross-referenced legislation or secondary legislation.',
            'Assess whether the law has achieved its objectives' => 'Assess whether the legislation has achieved its objectives',
            'What are the main objectives of this law?' => 'What are the main objectives of this legislation?',
            "Mirror the law's own terminology, structure, and drafting conventions rather than imposing another legal tradition's defaults." => "Mirror the legislation's own terminology, structure, and drafting conventions rather than imposing another legal tradition's defaults.",
            'Flag when the AI lacks the full text, a cross-referenced law, or secondary legislation needed for confidence.' => 'Flag when the AI lacks the full text, cross-referenced legislation, or secondary legislation needed for confidence.',
            'Do not assess whether the law has achieved its objectives. That is an analytical judgment that belongs in the Analysis tab (Tab 7) and depends on evidence beyond the legislation text.' => 'Do not assess whether the legislation has achieved its objectives. That is an analytical judgment that belongs in the Analysis tab (Tab 7) and depends on evidence beyond the legislation text.',
            'Do not assume jurisdiction-specific legal conventions. Different legal systems structure legislation differently. The AI should work from the text it has, not from assumptions about how laws "usually" work.' => 'Do not assume jurisdiction-specific legal conventions. Different legal systems structure legislation differently. The AI should work from the text it has, not from assumptions about how legislation "usually" works.',
            'Which implementing agencies are responsible for this law?' => 'Which implementing agencies are responsible for this legislation?',
            'Apply inclusion lenses such as gender, geography, disability, and socioeconomic impact when relevant to the law.' => 'Apply inclusion lenses such as gender, geography, disability, and socioeconomic impact when relevant to the legislation.',
            "Do not make assumptions about stakeholder positions. Knowing that an environmental NGO exists doesn't mean the AI knows what they think about the law." => "Do not make assumptions about stakeholder positions. Knowing that an environmental NGO exists doesn't mean the AI knows what they think about the legislation.",
            'Do not assess real-world impact with certainty. Impact assessment depends on qualitative and quantitative data that the AI may not have access to. The AI should frame impact observations as "the evidence suggests..." not "the law has achieved/failed..."' => 'Do not assess real-world impact with certainty. Impact assessment depends on qualitative and quantitative data that the AI may not have access to. The AI should frame impact observations as "the evidence suggests..." not "the legislation has achieved/failed..."',
        ]);
    }

    public function down(): void
    {
        $this->updateActiveLegislationPlaybook([
            "You're in Legislation. I can help you understand the structure of the legislation under review, identify its implementation obligations, and flag where secondary legislation may be required." => "You're in Legislation. I can help you understand the structure of the law under review, identify its implementation obligations, and flag where secondary legislation may be required.",
            'Explain the structure, key provisions, and internal architecture of the legislation under review.' => 'Explain the structure, key provisions, and internal architecture of the law under review.',
            'Flag where understanding a provision depends on missing cross-referenced legislation or secondary legislation.' => 'Flag where understanding a provision depends on missing cross-referenced laws or secondary legislation.',
            'Assess whether the legislation has achieved its objectives' => 'Assess whether the law has achieved its objectives',
            'What are the main objectives of this legislation?' => 'What are the main objectives of this law?',
            "Mirror the legislation's own terminology, structure, and drafting conventions rather than imposing another legal tradition's defaults." => "Mirror the law's own terminology, structure, and drafting conventions rather than imposing another legal tradition's defaults.",
            'Flag when the AI lacks the full text, cross-referenced legislation, or secondary legislation needed for confidence.' => 'Flag when the AI lacks the full text, a cross-referenced law, or secondary legislation needed for confidence.',
            'Do not assess whether the legislation has achieved its objectives. That is an analytical judgment that belongs in the Analysis tab (Tab 7) and depends on evidence beyond the legislation text.' => 'Do not assess whether the law has achieved its objectives. That is an analytical judgment that belongs in the Analysis tab (Tab 7) and depends on evidence beyond the legislation text.',
            'Do not assume jurisdiction-specific legal conventions. Different legal systems structure legislation differently. The AI should work from the text it has, not from assumptions about how legislation "usually" works.' => 'Do not assume jurisdiction-specific legal conventions. Different legal systems structure legislation differently. The AI should work from the text it has, not from assumptions about how laws "usually" work.',
            'Which implementing agencies are responsible for this legislation?' => 'Which implementing agencies are responsible for this law?',
            'Apply inclusion lenses such as gender, geography, disability, and socioeconomic impact when relevant to the legislation.' => 'Apply inclusion lenses such as gender, geography, disability, and socioeconomic impact when relevant to the law.',
            "Do not make assumptions about stakeholder positions. Knowing that an environmental NGO exists doesn't mean the AI knows what they think about the legislation." => "Do not make assumptions about stakeholder positions. Knowing that an environmental NGO exists doesn't mean the AI knows what they think about the law.",
            'Do not assess real-world impact with certainty. Impact assessment depends on qualitative and quantitative data that the AI may not have access to. The AI should frame impact observations as "the evidence suggests..." not "the legislation has achieved/failed..."' => 'Do not assess real-world impact with certainty. Impact assessment depends on qualitative and quantitative data that the AI may not have access to. The AI should frame impact observations as "the evidence suggests..." not "the law has achieved/failed..."',
        ]);
    }

    /**
     * @param  array<string, string>  $replacements
     */
    private function updateActiveLegislationPlaybook(array $replacements): void
    {
        $playbook = DB::table('assistant_tab_playbooks')
            ->where('tab_key', 'legislation')
            ->first();

        if ($playbook === null || $playbook->active_version_id === null) {
            return;
        }

        $version = DB::table('assistant_tab_playbook_versions')
            ->where('id', $playbook->active_version_id)
            ->first();

        if ($version === null) {
            return;
        }

        $updates = [];

        foreach (['intro', 'objectives', 'allowed_capabilities', 'disallowed_capabilities', 'suggested_prompts', 'rules', 'guardrails', 'response_style'] as $column) {
            $value = $version->{$column};

            if ($value === null) {
                continue;
            }

            if ($column === 'intro') {
                $updates[$column] = strtr((string) $value, $replacements);

                continue;
            }

            $decoded = json_decode((string) $value, true);

            if (! is_array($decoded)) {
                continue;
            }

            $updates[$column] = json_encode(
                array_map(
                    static fn (mixed $item): mixed => is_string($item) ? strtr($item, $replacements) : $item,
                    $decoded,
                ),
            );
        }

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = now();

        DB::table('assistant_tab_playbook_versions')
            ->where('id', $version->id)
            ->update($updates);
    }
};
