# PLS Domain Model

## Overview

The PLS Bot prototype is centered on a single record: `PlsReview`.

A review represents one post-legislative scrutiny inquiry. Almost every other record in the system exists to support that review. In practice, this means the review acts as the main container for workflow progress, source material, analysis, engagement activity, and final outputs.

This structure is intentional. It keeps the model focused on the real unit of work: one review moving through a defined PLS process.

## Ownership Model

A `PlsReview` is owned by the user who creates it.

Ownership is distinct from organizational context. A review may be associated with a `ReviewGroup`, but that group does not own the review. It represents where the review is situated within an institution, not who is responsible for it.

This separation allows:

- reviews to exist before being assigned to a group
- reviews to be managed by individuals or teams outside formal review-group structures
- flexibility across different parliamentary and organizational models

## Core Entity: PlsReview

`PlsReview` is the anchor record for the whole prototype.

It represents a single PLS review of one law, or a closely related group of laws, within a particular institutional context. It gives the system a stable place to store the review's identity, progress, and related records.

Key fields include:

- `title`: the working name of the review
- `description`: a short explanation of what the review covers
- `status`: the overall lifecycle state of the review
- `current_step_number`: where the review currently sits in the workflow
- `start_date`: when the review began, if known
- `completed_at`: when the review was finished
- institutional context such as review group (optional), legislature, jurisdiction, and country
- ownership via the user who created the review

The review lifecycle is simple:

- `draft`: the review has been created but work has not meaningfully started
- `active`: the review is underway and progressing through the workflow
- `completed`: the review has finished its workflow and outputs

Everything else in the model either belongs directly to a review or helps explain what happened within that review.

## Workflow Entities

`PlsReviewStep` represents one step in the review workflow.

Each review is expected to move through an ordered set of 11 steps. The step records make that workflow visible and trackable without turning the review itself into a large free-form checklist.

Each step stores information such as:

- its position in the workflow
- a stable step key
- step status
- when it started
- when it was completed
- optional notes

This document does not repeat the full 11-step workflow in detail. Instead, it assumes the canonical step sequence described in the workflow document and uses `PlsReviewStep` to connect that sequence to a specific review.

In short:

- one `PlsReview` has many `PlsReviewStep` records
- each step belongs to exactly one review
- the review's `current_step_number` points to the step the team is currently working on

## Evidence and Source Material

This layer captures the records that hold source material and traceable supporting information.

### Document

`Document` represents an uploaded or stored source file connected to a review.

Examples might include legislation text, briefs, consultation notes, submissions, background papers, or report files. A document stores the practical metadata needed to manage that file, such as title, type, file location, MIME type, size, and summary.

Documents belong to a review because source material only makes sense in the context of a specific inquiry.

### DocumentChunk

`DocumentChunk` represents a smaller piece of a document.

Documents may be chunked after upload so the system can work with them in smaller units for search, retrieval, summarization, or later AI features. A chunk is not a standalone business record. It exists only as part of a document.

### EvidenceItem

`EvidenceItem` represents a structured piece of evidence that the team wants to capture explicitly.

An evidence item can summarize a fact, observation, or point that matters to the review. It may link back to a `Document`, but it does not have to. This gives the team a way to record evidence even when it comes from discussion, synthesis, or manual review work rather than a single source file.

Together, these records support a simple pattern:

- documents are uploaded to the review
- documents may be chunked for retrieval and analysis
- evidence items capture the specific points that matter and can link back to source material

## Analysis Layer

This layer captures what the review concludes after examining the evidence.

### Finding

`Finding` represents a conclusion drawn during the review.

A finding is more than a quote or note. It expresses what the review team believes the evidence shows. Findings are therefore the bridge between raw source material and formal outputs.

Typical fields include a title, type, summary, and fuller detail.

### Recommendation

`Recommendation` represents an action or response proposed because of a finding.

Recommendations are linked to findings so the reasoning chain remains clear: evidence informs findings, and findings lead to recommendations.

This separation matters because it allows the system to distinguish between:

- what was observed
- what the review concluded
- what the review suggests should happen next

## Reporting Layer

This layer captures the formal outputs produced by the review and the responses they receive.

### Report

`Report` represents an output of the review.

This could be a draft report, an interim output, or a published final report. Reports belong to the review and may optionally link to a `Document` record when there is an associated file.

Reports are separate from general documents because they are not just source material. They are official outputs produced by the review process.

### GovernmentResponse

`GovernmentResponse` represents a response to a report.

It is tracked separately from the report itself because it is a different kind of record: the report is the review's output, while the government response is an external reaction to that output. A government response belongs to a review, belongs to a report, and may optionally link to a supporting document.

This keeps reporting history clear:

- reports capture what the review issued
- government responses capture what came back later

## Stakeholders and Consultations

This layer captures who was involved and how engagement was recorded.

### Stakeholder

`Stakeholder` represents a participant, interested party, or group affected by the legislation or the review.

Stakeholders can include civil society groups, experts, affected communities, professional bodies, or any other party the review team wants to track.

### ImplementingAgency

`ImplementingAgency` represents an agency or body responsible for implementing, administering, or enforcing the legislation.

This is tracked separately from general stakeholders because implementing agencies often play a distinct role in post-legislative scrutiny.

### Consultation

`Consultation` represents a consultation activity held as part of the review.

Examples include hearings, meetings, workshops, or public engagement sessions. A consultation belongs to the review and may optionally link to a document, such as notes or a meeting record.

### Submission

`Submission` represents material submitted into the review process.

A submission belongs to the review and to a specific stakeholder, which preserves a clear link between engagement and source material. It may also optionally link to a document when a file was provided.

Together, these records support engagement tracking:

- stakeholders identify who matters to the review
- implementing agencies identify bodies responsible for delivery
- consultations record planned or completed engagement activities
- submissions capture what participants actually provided

## Legislation

This layer captures what law is under review and what that law was meant to achieve.

### Legislation

`Legislation` represents the law that is being scrutinized.

The review can be linked to one or more legislation records, which allows the model to handle cases where a review covers a main act plus related instruments or amendments.

### LegislationObjective

`LegislationObjective` represents an intended outcome of the legislation.

Objectives help the review team express what the law was supposed to do. That matters because post-legislative scrutiny is usually concerned with questions such as whether the law was implemented as intended and whether it achieved its intended results.

Objectives belong to both the legislation record and the review context so they can be interpreted within a specific inquiry.

## Relationships Overview

- A `PlsReview` has many `PlsReviewStep` records.
- A `PlsReview` has many `Document` records.
- A `Document` has many `DocumentChunk` records.
- A `PlsReview` has many `EvidenceItem` records.
- An `EvidenceItem` may link to one `Document`.
- A `PlsReview` has many `Finding` records.
- A `Finding` has many `Recommendation` records.
- A `Recommendation` belongs to one `Finding`.
- A `PlsReview` has many `Report` records.
- A `Report` may link to one `Document`.
- A `Report` may have one or more `GovernmentResponse` records over time, though the common case may be a single response.
- A `GovernmentResponse` belongs to one `Report`.
- A `GovernmentResponse` may link to one `Document`.
- A `PlsReview` has many `Stakeholder` records.
- A `Stakeholder` has many `Submission` records.
- A `PlsReview` has many `ImplementingAgency` records.
- A `PlsReview` has many `Consultation` records.
- A `Consultation` may link to one `Document`.
- A `PlsReview` has many `Submission` records.
- A `Submission` belongs to one `Stakeholder`.
- A `Submission` may link to one `Document`.
- A `PlsReview` can be linked to one or more `Legislation` records.
- A `Legislation` has many `LegislationObjective` records.
- A `PlsReview` has many `LegislationObjective` records within its review context.

## Optional vs Required Relationships

The basic rule is that the review is the required anchor. Most records are not meaningful on their own and should exist in the context of a `PlsReview`.

Required relationships:

- `PlsReviewStep` requires a `PlsReview`
- `Document` requires a `PlsReview`
- `DocumentChunk` requires a `Document`
- `EvidenceItem` requires a `PlsReview`
- `Finding` requires a `PlsReview`
- `Recommendation` requires both a `PlsReview` and a `Finding`
- `Stakeholder` requires a `PlsReview`
- `ImplementingAgency` requires a `PlsReview`
- `Consultation` requires a `PlsReview`
- `Submission` requires a `PlsReview` and a `Stakeholder`
- `Report` requires a `PlsReview`
- `GovernmentResponse` requires a `PlsReview` and a `Report`
- `LegislationObjective` requires both `Legislation` and `PlsReview`

Optional relationships:

- a review may optionally belong to a review group (which may represent a review team, office, or other designation)
- a consultation may optionally link to a document
- a submission may optionally link to a document
- an evidence item may optionally link to a document
- a report may optionally link to a document
- a government response may optionally link to a document
- a review may involve one or more legislation records, depending on scope

Conceptually, review group assignment is optional at the domain level. A review may exist without being assigned to any specific group.

For display purposes, institutional assignment should use this fallback hierarchy:

- review group
- legislature
- jurisdiction
- `Unassigned`

This means the UI should prefer the most specific available institutional label without making review group assignment the only way to understand where a review sits.

## Design Principles

This model follows a few simple principles.

Workflow-driven model:

The review is not just a folder of records. It moves through a known sequence of steps, and the data model keeps that sequence explicit.

Structured records over free-form text:

Important review content is stored in distinct record types such as findings, recommendations, consultations, and reports. That makes the information easier to track, compare, filter, and reuse.

Separation of evidence, analysis, and reporting:

Documents and evidence are not the same as findings. Findings are not the same as recommendations. Reports are not the same as government responses. Keeping these layers separate preserves traceability.

Designed for future AI features:

The model is organized so an AI system can understand what stage the review is in, what source material exists, what conclusions have already been reached, and what outputs still need to be drafted.

## Why This Model Supports AI

This structure is useful for future AI work because it gives the system clear anchors instead of one large blob of text.

Document retrieval:

Documents and document chunks create a clean foundation for search, summarization, and retrieval of relevant source passages.

Step-aware assistance:

Because each review has explicit workflow steps and a current step, AI assistance can be tailored to the phase of work the team is actually in.

Generating findings and recommendations:

Evidence items, findings, and recommendations are distinct records, which makes it easier to move from source material to conclusions and from conclusions to proposed actions in a traceable way.

Drafting reports:

Reports sit on top of the earlier layers, so AI can draw from legislation, consultation activity, evidence, findings, and recommendations when helping draft formal outputs.

## Summary

This document defines the core domain model for the PLS Bot prototype with `PlsReview` as the central record. It explains the main entities, how they relate to one another, which links are required or optional, and how the overall structure supports both the current PLS workflow and future AI-assisted features.
