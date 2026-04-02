<?php

namespace Database\Seeders;

use App\Domain\Analysis\Enums\EvidenceType;
use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\EvidenceItem;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Documents\DocumentChunk;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\ReviewGroupType;
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
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class PlsReviewDemoSeeder extends Seeder
{
    public function run(CreatePlsReview $createPlsReview): void
    {
        $this->seedBelizeReview($createPlsReview, $this->ownerForCountry('BLZ', 'belize'));
        $this->seedUgandaReview($createPlsReview, $this->ownerForCountry('UGA', 'uganda'));
        $this->seedTennesseeReview($createPlsReview, $this->ownerForCountry('USA', 'tennessee'));
    }

    private function seedBelizeReview(CreatePlsReview $createPlsReview, User $owner): void
    {
        $reviewGroup = ReviewGroup::query()
            ->where('type', ReviewGroupType::Committee)
            ->where('name', 'Governance and Public Service Unit')
            ->firstOrFail();

        $review = $this->findOrCreateReview(
            $createPlsReview,
            $reviewGroup,
            $owner,
            'Post-Legislative Review of the Access to Information Act',
            'Assesses whether the access to information framework improved proactive disclosure and response performance across public authorities.',
            '2026-01-15',
        );

        $legislation = Legislation::query()->updateOrCreate(
            [
                'jurisdiction_id' => $review->jurisdiction_id,
                'title' => 'Access to Information Act',
            ],
            [
                'short_title' => 'ATI Act',
                'legislation_type' => LegislationType::Act,
                'date_enacted' => '2021-12-01',
                'summary' => 'Establishes rights of access to information held by public authorities and sets disclosure obligations.',
            ],
        );

        PlsReviewLegislation::query()->updateOrCreate(
            [
                'pls_review_id' => $review->id,
                'legislation_id' => $legislation->id,
            ],
            [
                'relationship_type' => ReviewLegislationRelationshipType::Primary,
            ],
        );

        LegislationObjective::query()->updateOrCreate(
            [
                'pls_review_id' => $review->id,
                'legislation_id' => $legislation->id,
                'title' => 'Improve public access to government information',
            ],
            [
                'description' => 'Ensure timely disclosure of records and strengthen transparency obligations across ministries and public bodies.',
            ],
        );

        $legislationText = $this->upsertDocument(
            $review,
            'belize/access-to-information-act.pdf',
            'Access to Information Act',
            DocumentType::LegislationText,
            'application/pdf',
            482311,
            'Official text of the Access to Information Act used as the baseline review document.',
        );

        $implementationReport = $this->upsertDocument(
            $review,
            'belize/ministry-implementation-report.pdf',
            'Ministry Implementation Progress Report',
            DocumentType::ImplementationReport,
            'application/pdf',
            218455,
            'Administrative report summarizing implementation milestones and operational bottlenecks.',
        );

        $finalReportDocument = $this->upsertDocument(
            $review,
            'belize/final-pls-report.pdf',
            'Final PLS Report on the Access to Information Act',
            DocumentType::GroupReport,
            'application/pdf',
            611004,
            'Final review-group report consolidating evidence, findings, and recommendations from the review.',
        );

        $this->upsertChunk($legislationText, 0, 'Defines access rights, timelines, and disclosure duties for public authorities.');
        $this->upsertChunk($implementationReport, 0, 'Highlights staffing shortages and uneven publication practices across ministries.');

        $civilSociety = $this->upsertStakeholder(
            $review,
            'Belize Transparency Initiative',
            StakeholderType::Ngo,
            ['email' => 'info@belizetransparency.org', 'phone' => '+501-555-0101'],
        );

        $this->upsertImplementingAgency(
            $review,
            'Ministry of the Public Service and Governance',
            ImplementingAgencyType::Ministry,
        );

        $submissionDocument = $this->upsertDocument(
            $review,
            'belize/transparency-initiative-submission.pdf',
            'Belize Transparency Initiative Submission',
            DocumentType::ConsultationSubmission,
            'application/pdf',
            124210,
            'Written submission from civil society on delays and disclosure quality.',
        );

        $this->upsertConsultation(
            $review,
            'Roundtable on disclosure compliance',
            ConsultationType::Roundtable,
            '2026-02-20 10:00:00',
            'Roundtable with ministries, media representatives, and civil society groups.',
            $submissionDocument,
        );

        $this->upsertSubmission(
            $review,
            $civilSociety,
            $submissionDocument,
            '2026-02-18 09:00:00',
            'Submission requesting stronger publication standards and deadline tracking.',
        );

        $this->upsertEvidenceItem(
            $review,
            $implementationReport,
            'Implementation performance dashboard',
            EvidenceType::Statistical,
            'Compiled response-time and disclosure-rate data across core public authorities.',
        );

        $finding = $this->upsertFinding(
            $review,
            'Proactive disclosure remains uneven across ministries',
            FindingType::ImplementationGap,
            'Several public authorities are not consistently publishing mandatory classes of information.',
            'The review found uneven compliance with publication schemes, particularly outside central ministries.',
        );

        $this->upsertRecommendation(
            $review,
            $finding,
            'Issue a standard disclosure directive',
            RecommendationType::ImproveImplementation,
            'The responsible ministry should issue a directive with standard publication templates and quarterly compliance reporting.',
        );

        $report = $this->upsertReport(
            $review,
            'PLS Report on the Access to Information Act',
            ReportType::FinalReport,
            ReportStatus::Published,
            $finalReportDocument,
            '2026-04-30 09:00:00',
        );

        $responseDocument = $this->upsertDocument(
            $review,
            'belize/government-response-ati.pdf',
            'Government Response to the ATI Act Review',
            DocumentType::GovernmentResponse,
            'application/pdf',
            214903,
            'Formal response setting out implementation commitments and timelines.',
        );

        $this->upsertGovernmentResponse(
            $review,
            $report,
            $responseDocument,
            GovernmentResponseStatus::Received,
            '2026-06-10 12:00:00',
        );
    }

    private function seedUgandaReview(CreatePlsReview $createPlsReview, User $owner): void
    {
        $reviewGroup = ReviewGroup::query()
            ->where('type', ReviewGroupType::Committee)
            ->where('name', 'Legal and Parliamentary Affairs Office')
            ->firstOrFail();

        $review = $this->findOrCreateReview(
            $createPlsReview,
            $reviewGroup,
            $owner,
            'Post-Legislative Scrutiny of the Public Finance Management Act',
            'Reviews fiscal transparency, agency reporting discipline, and follow-through on delegated regulations under the finance management framework.',
            '2026-02-03',
        );

        $legislation = Legislation::query()->updateOrCreate(
            [
                'jurisdiction_id' => $review->jurisdiction_id,
                'title' => 'Public Finance Management Act',
            ],
            [
                'short_title' => 'PFM Act',
                'legislation_type' => LegislationType::Act,
                'date_enacted' => '2015-03-06',
                'summary' => 'Provides the legal framework for fiscal management, budget execution, and accountability reporting.',
            ],
        );

        PlsReviewLegislation::query()->updateOrCreate(
            [
                'pls_review_id' => $review->id,
                'legislation_id' => $legislation->id,
            ],
            [
                'relationship_type' => ReviewLegislationRelationshipType::Primary,
            ],
        );

        LegislationObjective::query()->updateOrCreate(
            [
                'pls_review_id' => $review->id,
                'legislation_id' => $legislation->id,
                'title' => 'Strengthen fiscal accountability and budget discipline',
            ],
            [
                'description' => 'Provide clear accountability rules for budget formulation, execution, and reporting across spending entities.',
            ],
        );

        $budgetReport = $this->upsertDocument(
            $review,
            'uganda/budget-execution-review.pdf',
            'Budget Execution Review',
            DocumentType::ImplementationReport,
            'application/pdf',
            337210,
            'Implementation assessment focused on quarterly reporting discipline and delegated guidance.',
        );

        $hearingTranscript = $this->upsertDocument(
            $review,
            'uganda/hearing-transcript-pfm.txt',
            'Hearing Transcript on Fiscal Oversight',
            DocumentType::HearingTranscript,
            'text/plain',
            45320,
            'Transcript from the hearing with the Ministry of Finance and oversight actors.',
        );

        $briefingNote = $this->upsertDocument(
            $review,
            'uganda/public-briefing-note.pdf',
            'Public Briefing Note on the PFM Review',
            DocumentType::PolicyReport,
            'application/pdf',
            145992,
            'Short public-facing note summarizing the principal findings and next steps.',
        );

        $this->upsertChunk($budgetReport, 0, 'Quarterly releases improved, but reporting quality remains inconsistent across spending agencies.');
        $this->upsertChunk($hearingTranscript, 0, 'Witnesses highlighted reporting delays and weak follow-up on delegated regulations.');

        $ministry = $this->upsertStakeholder(
            $review,
            'Ministry of Finance, Planning and Economic Development',
            StakeholderType::Ministry,
            ['email' => 'finance@finance.go.ug'],
        );

        $this->upsertImplementingAgency(
            $review,
            'Office of the Accountant General',
            ImplementingAgencyType::Agency,
        );

        $this->upsertConsultation(
            $review,
            'Public hearing on delegated fiscal regulations',
            ConsultationType::Hearing,
            '2026-03-12 14:00:00',
            'Evidence session with the finance ministry, civil society budget monitors, and the Auditor General.',
            $hearingTranscript,
        );

        $this->upsertSubmission(
            $review,
            $ministry,
            $budgetReport,
            '2026-03-05 16:00:00',
            'Ministry submission describing implementation progress and outstanding regulatory issues.',
        );

        $this->upsertEvidenceItem(
            $review,
            $hearingTranscript,
            'Hearing evidence',
            EvidenceType::Testimony,
            'Recorded evidence from ministry officials, auditors, and civil society witnesses.',
        );

        $finding = $this->upsertFinding(
            $review,
            'Delegated regulations were not updated in line with reporting reforms',
            FindingType::AdministrativeIssue,
            'Secondary rules lag behind the operational reforms expected by the Act.',
            'The review found that guidance and reporting templates have not been updated consistently to reflect statutory accountability changes.',
        );

        $this->upsertRecommendation(
            $review,
            $finding,
            'Table updated delegated regulations within one session',
            RecommendationType::AmendLegislation,
            'The ministry should table revised delegated regulations and standard reporting templates before the next budget cycle.',
        );

        $report = $this->upsertReport(
            $review,
            'Final Report on the Public Finance Management Act Review',
            ReportType::FinalReport,
            ReportStatus::Published,
            $briefingNote,
            '2026-05-20 11:00:00',
        );

        $this->upsertGovernmentResponse(
            $review,
            $report,
            null,
            GovernmentResponseStatus::Requested,
            null,
        );
    }

    private function seedTennesseeReview(CreatePlsReview $createPlsReview, User $owner): void
    {
        $reviewGroup = ReviewGroup::query()
            ->where('type', ReviewGroupType::Committee)
            ->where('name', 'State and Local Government Office')
            ->firstOrFail();

        $review = $this->findOrCreateReview(
            $createPlsReview,
            $reviewGroup,
            $owner,
            'Review of the Tennessee Public Records Act Implementation',
            'Examines response times, fee practices, and local implementation consistency under the public records framework.',
            '2026-01-28',
        );

        $legislation = Legislation::query()->updateOrCreate(
            [
                'jurisdiction_id' => $review->jurisdiction_id,
                'title' => 'Tennessee Public Records Act',
            ],
            [
                'short_title' => 'TPRA',
                'legislation_type' => LegislationType::Act,
                'date_enacted' => '1957-03-08',
                'summary' => 'Provides public access to state and local government records with specified exemptions and procedures.',
            ],
        );

        PlsReviewLegislation::query()->updateOrCreate(
            [
                'pls_review_id' => $review->id,
                'legislation_id' => $legislation->id,
            ],
            [
                'relationship_type' => ReviewLegislationRelationshipType::Primary,
            ],
        );

        $countyAssociation = $this->upsertStakeholder(
            $review,
            'Tennessee County Services Association',
            StakeholderType::IndustryGroup,
            ['email' => 'policy@tncounties.org'],
        );

        $consultationDocument = $this->upsertDocument(
            $review,
            'tennessee/workshop-summary.pdf',
            'Workshop Summary on Public Records Compliance',
            DocumentType::PolicyReport,
            'application/pdf',
            168990,
            'Summary document from the workshop with county and municipal clerks.',
        );

        $this->upsertConsultation(
            $review,
            'Workshop with county and municipal records officers',
            ConsultationType::Workshop,
            '2026-03-25 09:30:00',
            'Discussion on staffing, fee schedules, and request tracking practices.',
            $consultationDocument,
        );

        $this->upsertSubmission(
            $review,
            $countyAssociation,
            $consultationDocument,
            '2026-03-24 15:00:00',
            'Submission outlining implementation differences between large and small local authorities.',
        );
    }

    private function findOrCreateReview(
        CreatePlsReview $createPlsReview,
        ReviewGroup $reviewGroup,
        User $owner,
        string $title,
        string $description,
        string $startDate,
    ): PlsReview {
        $reviewGroup->loadMissing('legislature.jurisdiction.country');

        $existingReview = PlsReview::query()
            ->where('review_group_id', $reviewGroup->id)
            ->where('title', $title)
            ->first();

        if ($existingReview !== null) {
            return $existingReview->load('steps');
        }

        return $createPlsReview->create(new CreatePlsReviewData(
            legislatureId: $reviewGroup->legislature_id,
            reviewGroupId: $reviewGroup->id,
            title: $title,
            description: $description,
            startDate: CarbonImmutable::parse($startDate),
            createdBy: $owner->id,
        ));
    }

    private function upsertDocument(
        PlsReview $review,
        string $storagePath,
        string $title,
        DocumentType $documentType,
        ?string $mimeType,
        ?int $fileSize,
        string $summary,
    ): Document {
        return Document::query()->updateOrCreate(
            ['storage_path' => $storagePath],
            [
                'pls_review_id' => $review->id,
                'title' => $title,
                'document_type' => $documentType,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'summary' => $summary,
                'metadata' => ['seed' => 'pls-demo'],
            ],
        );
    }

    private function upsertChunk(Document $document, int $chunkIndex, string $content): void
    {
        DocumentChunk::query()->updateOrCreate(
            ['document_id' => $document->id, 'chunk_index' => $chunkIndex],
            [
                'content' => $content,
                'token_count' => str_word_count($content),
                'embedding' => null,
                'metadata' => ['seed' => 'pls-demo'],
            ],
        );
    }

    /**
     * @param  array<string, string>  $contactDetails
     */
    private function upsertStakeholder(
        PlsReview $review,
        string $name,
        StakeholderType $stakeholderType,
        array $contactDetails,
    ): Stakeholder {
        return Stakeholder::query()->updateOrCreate(
            ['pls_review_id' => $review->id, 'name' => $name],
            [
                'stakeholder_type' => $stakeholderType,
                'contact_details' => $contactDetails,
            ],
        );
    }

    private function upsertImplementingAgency(
        PlsReview $review,
        string $name,
        ImplementingAgencyType $agencyType,
    ): ImplementingAgency {
        return ImplementingAgency::query()->updateOrCreate(
            ['pls_review_id' => $review->id, 'name' => $name],
            ['agency_type' => $agencyType],
        );
    }

    private function upsertConsultation(
        PlsReview $review,
        string $title,
        ConsultationType $consultationType,
        ?string $heldAt,
        string $summary,
        ?Document $document,
    ): Consultation {
        return Consultation::query()->updateOrCreate(
            ['pls_review_id' => $review->id, 'title' => $title],
            [
                'consultation_type' => $consultationType,
                'held_at' => $heldAt,
                'summary' => $summary,
                'document_id' => $document?->id,
            ],
        );
    }

    private function upsertSubmission(
        PlsReview $review,
        Stakeholder $stakeholder,
        ?Document $document,
        ?string $submittedAt,
        string $summary,
    ): Submission {
        return Submission::query()->updateOrCreate(
            [
                'pls_review_id' => $review->id,
                'stakeholder_id' => $stakeholder->id,
            ],
            [
                'document_id' => $document?->id,
                'submitted_at' => $submittedAt,
                'summary' => $summary,
            ],
        );
    }

    private function upsertEvidenceItem(
        PlsReview $review,
        ?Document $document,
        string $title,
        EvidenceType $evidenceType,
        string $description,
    ): EvidenceItem {
        return EvidenceItem::query()->updateOrCreate(
            ['pls_review_id' => $review->id, 'title' => $title],
            [
                'document_id' => $document?->id,
                'evidence_type' => $evidenceType,
                'description' => $description,
            ],
        );
    }

    private function upsertFinding(
        PlsReview $review,
        string $title,
        FindingType $findingType,
        string $summary,
        string $detail,
    ): Finding {
        return Finding::query()->updateOrCreate(
            ['pls_review_id' => $review->id, 'title' => $title],
            [
                'finding_type' => $findingType,
                'summary' => $summary,
                'detail' => $detail,
            ],
        );
    }

    private function upsertRecommendation(
        PlsReview $review,
        Finding $finding,
        string $title,
        RecommendationType $recommendationType,
        string $description,
    ): Recommendation {
        return Recommendation::query()->updateOrCreate(
            ['finding_id' => $finding->id, 'title' => $title],
            [
                'pls_review_id' => $review->id,
                'recommendation_type' => $recommendationType,
                'description' => $description,
            ],
        );
    }

    private function upsertReport(
        PlsReview $review,
        string $title,
        ReportType $reportType,
        ReportStatus $status,
        ?Document $document,
        ?string $publishedAt,
    ): Report {
        return Report::query()->updateOrCreate(
            ['pls_review_id' => $review->id, 'title' => $title],
            [
                'report_type' => $reportType,
                'status' => $status,
                'document_id' => $document?->id,
                'published_at' => $publishedAt,
            ],
        );
    }

    private function upsertGovernmentResponse(
        PlsReview $review,
        Report $report,
        ?Document $document,
        GovernmentResponseStatus $status,
        ?string $receivedAt,
    ): GovernmentResponse {
        return GovernmentResponse::query()->updateOrCreate(
            ['report_id' => $report->id],
            [
                'pls_review_id' => $review->id,
                'document_id' => $document?->id,
                'response_status' => $status,
                'received_at' => $receivedAt,
            ],
        );
    }

    private function ownerForCountry(string $iso3, string $suffix): User
    {
        $country = Country::query()->where('iso3', $iso3)->firstOrFail();

        return User::query()->updateOrCreate(
            ['email' => "pls-demo-{$suffix}@example.test"],
            [
                'name' => 'PLS Demo Reviewer',
                'password' => 'password',
                'country_id' => $country->id,
            ],
        );
    }
}
