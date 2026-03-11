<?php

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Support\PlsReviewWorkflow;

it('creates a review with seeded steps in the official order and initializes current step number', function () {
    $context = plsReviewContext([
        'title' => 'Post-Legislative Review of the Public Finance Management Act',
        'description' => 'Reviews fiscal transparency and delegated regulation follow-through.',
        'start_date' => '2026-03-10',
    ], [
        'country' => [
            'name' => 'Uganda',
            'iso2' => 'UG',
            'iso3' => 'UGA',
        ],
        'legislature' => [
            'name' => 'Parliament of Uganda',
            'legislature_type' => \App\Domain\Institutions\Enums\LegislatureType::Parliament,
        ],
        'committee' => [
            'name' => 'Committee on Legal and Parliamentary Affairs',
        ],
    ]);

    $review = $context['review'];

    $review->load('steps');

    expect($review->status)->toBe(PlsReviewStatus::Draft)
        ->and($review->current_step_number)->toBe(1)
        ->and($review->steps)->toHaveCount(11)
        ->and($review->steps->pluck('step_number')->all())->toBe(array_column(PlsReviewWorkflow::definitions(), 'number'))
        ->and($review->steps->pluck('step_key')->all())->toBe(array_column(PlsReviewWorkflow::definitions(), 'key'));

    $this->assertDatabaseHas('pls_reviews', [
        'id' => $review->id,
        'status' => PlsReviewStatus::Draft->value,
        'current_step_number' => 1,
    ]);
});

it('assigns the review to its committee, legislature, jurisdiction, and country hierarchy', function () {
    $context = plsReviewContext([
        'title' => 'Review of delegated legislation oversight',
    ], [
        'country' => [
            'name' => 'Uganda',
            'iso2' => 'UG',
            'iso3' => 'UGA',
        ],
        'legislature' => [
            'name' => 'Parliament of Uganda',
            'legislature_type' => \App\Domain\Institutions\Enums\LegislatureType::Parliament,
        ],
        'committee' => [
            'name' => 'Committee on Legal and Parliamentary Affairs',
        ],
    ]);

    $review = $context['review'];

    expect($review->committee->is($context['committee']))->toBeTrue()
        ->and($review->legislature->is($context['legislature']))->toBeTrue()
        ->and($review->jurisdiction->is($context['jurisdiction']))->toBeTrue()
        ->and($review->country->is($context['country']))->toBeTrue();
});

it('can link legislation and documents to a review', function () {
    $context = plsReviewContext([
        'title' => 'Review of the Access to Information Act',
    ], [
        'country' => [
            'name' => 'Uganda',
            'iso2' => 'UG',
            'iso3' => 'UGA',
        ],
        'legislature' => [
            'name' => 'Parliament of Uganda',
            'legislature_type' => \App\Domain\Institutions\Enums\LegislatureType::Parliament,
        ],
        'committee' => [
            'name' => 'Committee on Legal and Parliamentary Affairs',
        ],
    ]);
    $review = $context['review'];

    $legislation = Legislation::factory()->create([
        'jurisdiction_id' => $review->jurisdiction_id,
        'title' => 'Access to Information Act',
        'legislation_type' => LegislationType::Act,
    ]);

    $review->legislation()->attach($legislation->id, [
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Access to Information Act Text',
        'document_type' => DocumentType::LegislationText,
    ]);

    expect($review->legislation()->sole()->is($legislation))->toBeTrue()
        ->and($review->documents()->sole()->is($document))->toBeTrue()
        ->and($document->review->is($review))->toBeTrue();

    $this->assertDatabaseHas('pls_review_legislation', [
        'pls_review_id' => $review->id,
        'legislation_id' => $legislation->id,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);

    $this->assertDatabaseHas('documents', [
        'id' => $document->id,
        'pls_review_id' => $review->id,
        'document_type' => DocumentType::LegislationText->value,
    ]);
});

it('can associate findings to a review and recommendations to findings', function () {
    $context = plsReviewContext([
        'title' => 'Review of implementation bottlenecks',
    ], [
        'country' => [
            'name' => 'Uganda',
            'iso2' => 'UG',
            'iso3' => 'UGA',
        ],
        'legislature' => [
            'name' => 'Parliament of Uganda',
            'legislature_type' => \App\Domain\Institutions\Enums\LegislatureType::Parliament,
        ],
        'committee' => [
            'name' => 'Committee on Legal and Parliamentary Affairs',
        ],
    ]);
    $review = $context['review'];

    $finding = Finding::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Disclosure obligations are applied unevenly',
        'finding_type' => FindingType::ImplementationGap,
    ]);

    $recommendation = Recommendation::factory()->create([
        'pls_review_id' => $review->id,
        'finding_id' => $finding->id,
        'title' => 'Issue a standard disclosure directive',
        'recommendation_type' => RecommendationType::ImproveImplementation,
    ]);

    expect($review->findings()->sole()->is($finding))->toBeTrue()
        ->and($finding->review->is($review))->toBeTrue()
        ->and($finding->recommendations()->sole()->is($recommendation))->toBeTrue()
        ->and($review->recommendations()->sole()->is($recommendation))->toBeTrue()
        ->and($recommendation->finding->is($finding))->toBeTrue();

    $this->assertDatabaseHas('findings', [
        'id' => $finding->id,
        'pls_review_id' => $review->id,
        'finding_type' => FindingType::ImplementationGap->value,
    ]);

    $this->assertDatabaseHas('recommendations', [
        'id' => $recommendation->id,
        'pls_review_id' => $review->id,
        'finding_id' => $finding->id,
        'recommendation_type' => RecommendationType::ImproveImplementation->value,
    ]);
});

it('can associate reports to a review', function () {
    $context = plsReviewContext([
        'title' => 'Review of reporting obligations',
    ], [
        'country' => [
            'name' => 'Uganda',
            'iso2' => 'UG',
            'iso3' => 'UGA',
        ],
        'legislature' => [
            'name' => 'Parliament of Uganda',
            'legislature_type' => \App\Domain\Institutions\Enums\LegislatureType::Parliament,
        ],
        'committee' => [
            'name' => 'Committee on Legal and Parliamentary Affairs',
        ],
    ]);
    $review = $context['review'];

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Draft PLS Report',
        'document_type' => DocumentType::DraftReport,
    ]);

    $report = Report::factory()->create([
        'pls_review_id' => $review->id,
        'document_id' => $document->id,
        'title' => 'Draft PLS Report on Reporting Obligations',
        'report_type' => ReportType::DraftReport,
        'status' => ReportStatus::Draft,
    ]);

    expect($review->reports()->sole()->is($report))->toBeTrue()
        ->and($report->review->is($review))->toBeTrue()
        ->and($report->document->is($document))->toBeTrue();

    $this->assertDatabaseHas('reports', [
        'id' => $report->id,
        'pls_review_id' => $review->id,
        'document_id' => $document->id,
        'report_type' => ReportType::DraftReport->value,
        'status' => ReportStatus::Draft->value,
    ]);
});
