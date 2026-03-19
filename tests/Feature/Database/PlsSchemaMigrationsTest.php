<?php

use Illuminate\Support\Facades\Schema;

it('creates all prompt two pls tables', function () {
    $tables = [
        'countries',
        'jurisdictions',
        'legislatures',
        'review_groups',
        'pls_reviews',
        'pls_review_memberships',
        'pls_review_steps',
        'legislation',
        'pls_review_legislation',
        'legislation_objectives',
        'documents',
        'document_chunks',
        'evidence_items',
        'stakeholders',
        'implementing_agencies',
        'consultations',
        'submissions',
        'findings',
        'recommendations',
        'reports',
        'government_responses',
    ];

    foreach ($tables as $table) {
        expect(Schema::hasTable($table))->toBeTrue("Failed asserting that table [{$table}] exists.");
    }
});

it('creates the critical prompt two workflow and foreign key columns', function () {
    expect(Schema::hasColumns('jurisdictions', [
        'country_id',
        'slug',
        'jurisdiction_type',
        'parent_id',
    ]))->toBeTrue()
        ->and(Schema::hasColumns('pls_reviews', [
            'review_group_id',
            'legislature_id',
            'jurisdiction_id',
            'country_id',
            'created_by',
            'status',
            'current_step_number',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('pls_review_memberships', [
            'pls_review_id',
            'user_id',
            'role',
            'invited_by',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('review_groups', [
            'name',
            'type',
            'country_id',
            'jurisdiction_id',
            'legislature_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('pls_review_steps', [
            'pls_review_id',
            'step_number',
            'step_key',
            'status',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('pls_review_legislation', [
            'pls_review_id',
            'legislation_id',
            'relationship_type',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('documents', [
            'pls_review_id',
            'document_type',
            'storage_path',
            'metadata',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('document_chunks', [
            'document_id',
            'chunk_index',
            'embedding',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('submissions', [
            'pls_review_id',
            'stakeholder_id',
            'document_id',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('government_responses', [
            'pls_review_id',
            'report_id',
            'document_id',
            'response_status',
        ]))->toBeTrue();
});
