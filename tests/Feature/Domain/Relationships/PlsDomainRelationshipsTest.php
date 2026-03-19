<?php

use App\Domain\Analysis\EvidenceItem;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\LegislationObjective;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use App\Models\User;

it('loads the institutional relationships around a review', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature, 'reviewGroup' => $reviewGroup, 'owner' => $owner, 'review' => $review] = createReviewHierarchy();

    expect($country->jurisdictions()->sole()->is($jurisdiction))->toBeTrue()
        ->and($jurisdiction->country->is($country))->toBeTrue()
        ->and($jurisdiction->legislatures()->sole()->is($legislature))->toBeTrue()
        ->and($legislature->jurisdiction->is($jurisdiction))->toBeTrue()
        ->and($legislature->reviewGroups()->sole()->is($reviewGroup))->toBeTrue()
        ->and($reviewGroup->legislature->is($legislature))->toBeTrue()
        ->and($review->reviewGroup->is($reviewGroup))->toBeTrue()
        ->and($review->owner->is($owner))->toBeTrue()
        ->and($review->memberships()->sole()->user->is($owner))->toBeTrue()
        ->and($review->legislature->is($legislature))->toBeTrue()
        ->and($review->jurisdiction->is($jurisdiction))->toBeTrue()
        ->and($review->country->is($country))->toBeTrue()
        ->and($review->steps)->toHaveCount(11);
});

it('loads legislation, document, and objective relationships for a review', function () {
    ['jurisdiction' => $jurisdiction, 'review' => $review] = createReviewHierarchy();

    $legislation = Legislation::factory()->for($jurisdiction)->create([
        'title' => 'Access to Information Act',
    ]);

    $review->legislation()->attach($legislation, [
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);

    $objective = LegislationObjective::factory()->create([
        'legislation_id' => $legislation->id,
        'pls_review_id' => $review->id,
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'document_type' => DocumentType::LegislationText,
    ]);

    $chunk = DocumentChunk::factory()->create([
        'document_id' => $document->id,
        'chunk_index' => 0,
    ]);

    expect($jurisdiction->legislation()->sole()->is($legislation))->toBeTrue()
        ->and($review->legislation()->sole()->is($legislation))->toBeTrue()
        ->and($legislation->jurisdiction->is($jurisdiction))->toBeTrue()
        ->and($legislation->reviews()->sole()->is($review))->toBeTrue()
        ->and($legislation->objectives()->sole()->is($objective))->toBeTrue()
        ->and($document->review->is($review))->toBeTrue()
        ->and($document->legislation()->sole()->is($legislation))->toBeTrue()
        ->and($document->chunks()->sole()->is($chunk))->toBeTrue();
});

it('loads analysis, stakeholder, consultation, and reporting relationships', function () {
    ['review' => $review] = createReviewHierarchy();

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
    ]);

    $evidenceItem = EvidenceItem::factory()->create([
        'pls_review_id' => $review->id,
        'document_id' => $document->id,
    ]);

    $stakeholder = Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
    ]);

    $implementingAgency = ImplementingAgency::factory()->create([
        'pls_review_id' => $review->id,
    ]);

    $consultation = Consultation::factory()->create([
        'pls_review_id' => $review->id,
        'document_id' => $document->id,
    ]);

    $submission = Submission::factory()->create([
        'pls_review_id' => $review->id,
        'stakeholder_id' => $stakeholder->id,
        'document_id' => $document->id,
    ]);

    $finding = Finding::factory()->create([
        'pls_review_id' => $review->id,
    ]);

    $recommendation = Recommendation::factory()->create([
        'pls_review_id' => $review->id,
        'finding_id' => $finding->id,
    ]);

    $report = Report::factory()->create([
        'pls_review_id' => $review->id,
        'document_id' => $document->id,
    ]);

    $governmentResponse = GovernmentResponse::factory()->create([
        'pls_review_id' => $review->id,
        'report_id' => $report->id,
        'document_id' => $document->id,
    ]);

    expect($review->evidenceItems()->sole()->is($evidenceItem))->toBeTrue()
        ->and($evidenceItem->review->is($review))->toBeTrue()
        ->and($evidenceItem->document->is($document))->toBeTrue()
        ->and($review->stakeholders()->sole()->is($stakeholder))->toBeTrue()
        ->and($stakeholder->review->is($review))->toBeTrue()
        ->and($stakeholder->submissions()->sole()->is($submission))->toBeTrue()
        ->and($review->implementingAgencies()->sole()->is($implementingAgency))->toBeTrue()
        ->and($implementingAgency->review->is($review))->toBeTrue()
        ->and($review->consultations()->sole()->is($consultation))->toBeTrue()
        ->and($consultation->review->is($review))->toBeTrue()
        ->and($consultation->document->is($document))->toBeTrue()
        ->and($review->submissions()->sole()->is($submission))->toBeTrue()
        ->and($submission->review->is($review))->toBeTrue()
        ->and($submission->stakeholder->is($stakeholder))->toBeTrue()
        ->and($submission->document->is($document))->toBeTrue()
        ->and($review->findings()->sole()->is($finding))->toBeTrue()
        ->and($finding->review->is($review))->toBeTrue()
        ->and($finding->recommendations()->sole()->is($recommendation))->toBeTrue()
        ->and($review->recommendations()->sole()->is($recommendation))->toBeTrue()
        ->and($recommendation->review->is($review))->toBeTrue()
        ->and($recommendation->finding->is($finding))->toBeTrue()
        ->and($review->reports()->sole()->is($report))->toBeTrue()
        ->and($report->review->is($review))->toBeTrue()
        ->and($report->document->is($document))->toBeTrue()
        ->and($report->governmentResponses()->sole()->is($governmentResponse))->toBeTrue()
        ->and($review->governmentResponses()->sole()->is($governmentResponse))->toBeTrue()
        ->and($governmentResponse->review->is($review))->toBeTrue()
        ->and($governmentResponse->report->is($report))->toBeTrue();
});

function createReviewHierarchy(): array
{
    $country = Country::factory()->create([
        'name' => 'Uganda',
        'iso2' => 'UG',
        'iso3' => 'UGA',
    ]);

    $jurisdiction = Jurisdiction::factory()->for($country)->create([
        'name' => 'National',
        'slug' => 'national',
    ]);

    $legislature = Legislature::factory()->for($jurisdiction)->create([
        'name' => 'Parliament of Uganda',
        'slug' => 'parliament-of-uganda',
    ]);

    $reviewGroup = ReviewGroup::factory()->create([
        'country_id' => $country->id,
        'jurisdiction_id' => $jurisdiction->id,
        'legislature_id' => $legislature->id,
        'name' => 'Legal and Parliamentary Affairs Office',
        'type' => ReviewGroupType::Committee,
    ]);
    $owner = User::factory()->reviewer()->create();

    $review = app(CreatePlsReview::class)->create(
        new CreatePlsReviewData(
            legislatureId: $legislature->id,
            reviewGroupId: $reviewGroup->id,
            title: 'Review of statutory implementation',
            createdBy: $owner->id,
        ),
    );

    return [
        'country' => $country,
        'jurisdiction' => $jurisdiction,
        'legislature' => $legislature,
        'reviewGroup' => $reviewGroup,
        'owner' => $owner,
        'review' => $review,
    ];
}
