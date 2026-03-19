<?php

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\LegislationObjective;
use App\Domain\Legislation\PlsReviewLegislation;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\GovernmentResponse;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Enums\PlsStepStatus;
use App\Domain\Reviews\PlsReview;
use App\Domain\Reviews\PlsReviewStep;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use Database\Factories\Domain\Documents\DocumentFactory;
use Database\Factories\Domain\Institutions\CountryFactory;
use Database\Factories\Domain\Reviews\PlsReviewFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

it('defines the prompt one backed enums', function () {
    expect(JurisdictionType::cases())->toHaveCount(7)
        ->and(LegislatureType::cases())->toHaveCount(4)
        ->and(PlsReviewStatus::Draft->value)->toBe('draft')
        ->and(PlsStepStatus::InProgress->value)->toBe('in_progress')
        ->and(ReviewGroupType::Committee->value)->toBe('committee')
        ->and(DocumentType::FinalReport->value)->toBe('final_report')
        ->and(LegislationType::Regulation->value)->toBe('regulation')
        ->and(ReviewLegislationRelationshipType::Delegated->value)->toBe('delegated')
        ->and(StakeholderType::GovernmentAgency->value)->toBe('government_agency')
        ->and(ImplementingAgencyType::Authority->value)->toBe('authority')
        ->and(ConsultationType::PublicConsultation->value)->toBe('public_consultation')
        ->and(ReportType::BriefingNote->value)->toBe('briefing_note')
        ->and(ReportStatus::Published->value)->toBe('published')
        ->and(GovernmentResponseStatus::Overdue->value)->toBe('overdue');
});

it('defines the expected prompt one relationships', function () {
    expect((new Country)->jurisdictions())->toBeInstanceOf(HasMany::class)
        ->and((new Country)->reviews())->toBeInstanceOf(HasMany::class)
        ->and((new Country)->reviewGroups())->toBeInstanceOf(HasMany::class)
        ->and((new Jurisdiction)->country())->toBeInstanceOf(BelongsTo::class)
        ->and((new Jurisdiction)->children())->toBeInstanceOf(HasMany::class)
        ->and((new Jurisdiction)->reviewGroups())->toBeInstanceOf(HasMany::class)
        ->and((new Legislature)->jurisdiction())->toBeInstanceOf(BelongsTo::class)
        ->and((new Legislature)->reviewGroups())->toBeInstanceOf(HasMany::class)
        ->and((new ReviewGroup)->reviews())->toBeInstanceOf(HasMany::class)
        ->and((new PlsReview)->reviewGroup())->toBeInstanceOf(BelongsTo::class)
        ->and((new PlsReview)->ownerMembership())->toBeInstanceOf(HasOne::class)
        ->and((new PlsReview)->owner())->toBeInstanceOf(HasOneThrough::class)
        ->and((new PlsReview)->steps())->toBeInstanceOf(HasMany::class)
        ->and((new PlsReview)->legislation())->toBeInstanceOf(BelongsToMany::class)
        ->and((new PlsReviewStep)->review())->toBeInstanceOf(BelongsTo::class)
        ->and((new Legislation)->reviews())->toBeInstanceOf(BelongsToMany::class)
        ->and((new PlsReviewLegislation)->review())->toBeInstanceOf(BelongsTo::class)
        ->and((new LegislationObjective)->review())->toBeInstanceOf(BelongsTo::class)
        ->and((new Document)->chunks())->toBeInstanceOf(HasMany::class)
        ->and((new Document)->legislation())->toBeInstanceOf(BelongsToMany::class)
        ->and((new DocumentChunk)->document())->toBeInstanceOf(BelongsTo::class)
        ->and((new Stakeholder)->submissions())->toBeInstanceOf(HasMany::class)
        ->and((new ImplementingAgency)->review())->toBeInstanceOf(BelongsTo::class)
        ->and((new Consultation)->document())->toBeInstanceOf(BelongsTo::class)
        ->and((new Submission)->stakeholder())->toBeInstanceOf(BelongsTo::class)
        ->and((new Report)->governmentResponses())->toBeInstanceOf(HasMany::class)
        ->and((new GovernmentResponse)->report())->toBeInstanceOf(BelongsTo::class)
        ->and((new PlsReviewLegislation)->getTable())->toBe('pls_review_legislation');
});

it('registers enum, date, and json casts on the scaffolded models', function () {
    expect((new Jurisdiction)->getCasts())->toMatchArray([
        'jurisdiction_type' => JurisdictionType::class,
    ])->and((new ReviewGroup)->getCasts())->toMatchArray([
        'type' => ReviewGroupType::class,
    ])->and((new PlsReview)->getCasts())->toMatchArray([
        'status' => PlsReviewStatus::class,
        'start_date' => 'date',
        'completed_at' => 'datetime',
    ])->and((new PlsReviewStep)->getCasts())->toMatchArray([
        'status' => PlsStepStatus::class,
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ])->and((new Legislation)->getCasts())->toMatchArray([
        'legislation_type' => LegislationType::class,
        'date_enacted' => 'date',
    ])->and((new Document)->getCasts())->toMatchArray([
        'document_type' => DocumentType::class,
        'metadata' => 'array',
    ])->and((new DocumentChunk)->getCasts())->toMatchArray([
        'embedding' => 'array',
        'metadata' => 'array',
    ])->and((new Stakeholder)->getCasts())->toMatchArray([
        'stakeholder_type' => StakeholderType::class,
        'contact_details' => 'array',
    ])->and((new ImplementingAgency)->getCasts())->toMatchArray([
        'agency_type' => ImplementingAgencyType::class,
    ])->and((new Consultation)->getCasts())->toMatchArray([
        'consultation_type' => ConsultationType::class,
        'held_at' => 'datetime',
    ])->and((new Report)->getCasts())->toMatchArray([
        'report_type' => ReportType::class,
        'status' => ReportStatus::class,
        'published_at' => 'datetime',
    ])->and((new GovernmentResponse)->getCasts())->toMatchArray([
        'response_status' => GovernmentResponseStatus::class,
        'received_at' => 'datetime',
    ]);
});

it('provides factory defaults for the main prompt one models', function () {
    $countryDefinition = CountryFactory::new()->definition();
    $reviewDefinition = PlsReviewFactory::new()->definition();
    $documentDefinition = DocumentFactory::new()->definition();

    expect($countryDefinition)
        ->toHaveKeys(['name', 'iso2', 'iso3', 'default_locale'])
        ->and($reviewDefinition['status'])->toBe(PlsReviewStatus::Draft)
        ->and($reviewDefinition['current_step_number'])->toBe(1)
        ->and($reviewDefinition)->not->toHaveKey('legacy_group_id')
        ->and($documentDefinition['document_type'])->toBeInstanceOf(DocumentType::class)
        ->and($documentDefinition['metadata'])->toBeArray();
});
