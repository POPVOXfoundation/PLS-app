# PLS Workflow Model

This document describes how the application models the post-legislative scrutiny (PLS) process.

## Core Review Record

The central record is `PlsReview`.

A review represents one PLS inquiry and stores:

- optional institutional placement through `review_group_id`, `legislature_id`, `jurisdiction_id`, and `country_id`
- review metadata such as `title`, `slug`, `description`, and `start_date`
- lifecycle state through `status`, `current_step_number`, and `completed_at`

Review status is modeled with three operational states:

- `draft`
- `active`
- `completed`

When a review is created, it starts as:

- `status = draft`
- `current_step_number = 1`
- `completed_at = null`

## Step Model

Each review has 11 `PlsReviewStep` records.

Each step stores:

- `step_number`
- `step_key`
- `status`
- `started_at`
- `completed_at`
- `notes`

Step titles are not stored directly in the table. They are derived from the canonical workflow definition in `App\Domain\Reviews\Support\PlsReviewWorkflow`.

Step status is modeled as:

- `pending`
- `in_progress`
- `completed`
- `skipped`

A step is considered open if it is not terminal (`completed` or `skipped`).

## Canonical 11-Step Workflow

The system uses this ordered workflow:

1. Define the objectives and scope of PLS
2. Collect background information and prepare a data collection plan
3. Identify key stakeholders and prepare a consultation plan
4. Review implementing agencies and delegated legislation
5. Conduct consultation and public engagement activities
6. Analyse post-legislative scrutiny findings
7. Draft the PLS report
8. Disseminate the report and make it publicly accessible
9. Invite a response from the government to "comply or explain"
10. Conduct follow-up to the post-legislative scrutiny activities
11. Evaluate the post-legislative scrutiny inquiry results and process

Each step also has a stable key:

- `define_scope`
- `background_data_plan`
- `stakeholder_plan`
- `implementation_review`
- `consultations`
- `analysis`
- `draft_report`
- `dissemination`
- `government_response`
- `follow_up`
- `evaluation`

## Workflow Progression Rules

The application enforces a sequential workflow.

Only the first incomplete step can be started or completed.

That means:

- users cannot start a later step while an earlier step remains incomplete
- users cannot complete a later step while an earlier step remains incomplete
- `current_step_number` always points to the first incomplete step
- when all steps are complete, the review is marked `completed` and `current_step_number` remains at the final step

Operationally:

- starting the first incomplete step moves the review to `active`
- completing a step advances the review to the next incomplete step
- completing the last open step marks the review `completed`
- reopening a step moves that step back into progress, reopens the review, and resets later steps to `pending`

## Review Workspace Data Model

A review acts as the container for the main PLS working records.

The workspace attaches these record types to a review:

- legislation
- legislation objectives
- documents
- document chunks
- evidence items
- stakeholders
- implementing agencies
- consultations
- submissions
- findings
- recommendations
- reports
- government responses

This lets the review workspace function as the operational hub for the inquiry.

## How the Workspace Maps to the Workflow

The UI is organized into tabs, but the intent is workflow-driven rather than CRUD-driven.

Workspace areas map roughly like this:

- `Workflow`
  - overall step status, step detail, and progression context
- `Legislation`
  - core legislation linked to the review
  - most relevant in early scoping and implementation review work
- `Documents`
  - source materials, briefs, uploaded files, and supporting records
  - used across the lifecycle, especially background collection and reporting
- `Stakeholders`
  - stakeholder register and implementing agencies
  - most relevant in stakeholder planning and implementation review
- `Consultations`
  - consultation activity and submissions
  - most relevant in consultation and public engagement work
- `Analysis`
  - findings and recommendations
  - most relevant in analysis and draft report stages
- `Reports`
  - draft/final reports and government responses
  - most relevant in reporting, dissemination, government response, and follow-up

## Step-Aware Guidance

The review workspace includes step-aware guidance.

That guidance does not change the workflow model itself. It is a UI layer that:

- identifies the current step
- points the user to the most relevant workspace tab
- suggests the most likely next action for that phase

This is important for future AI features because it gives the system a clear notion of:

- current lifecycle stage
- expected user intent in that stage
- the records that matter most at that point in the review

## Institutional Assignment

A review may optionally be associated with a review group.

When displaying assignment context in the UI, the system uses the following fallback order:

1. review group name
2. legislature name
3. jurisdiction name
4. `Unassigned`

This is presentation logic only. The data model does not require a review-group relationship.

## Document Handling

Review documents are first-class records attached to the review.

Each document stores metadata such as:

- title
- document type
- storage path
- MIME type
- file size
- summary

The workspace supports direct file uploads for review documents. Documents can also be chunked into `document_chunks`, which is the current placeholder ingestion layer for later embedding and AI retrieval work.

## Reporting and Government Response

Reports are modeled separately from source documents.

A report can:

- belong to a review
- link to an optional document record
- carry a report type and publication status

Government responses are separate records linked to:

- the review
- a specific report
- an optional document

This separation matters for AI work later because it preserves the distinction between:

- evidence and source material
- review outputs
- executive/government responses

## Design Principles

The system is designed around a few core principles:

- Workflow-first: the 11-step PLS process is the backbone of the system
- Structured over free-form: key outputs (findings, recommendations, reports) are modeled explicitly
- Separation of concerns: evidence, analysis, and reporting are distinct layers
- Human-led, AI-assisted: the system supports users without replacing judgment

## Why This Structure Helps Future AI Features

The current model gives AI features several useful anchors:

- a canonical 11-step workflow with explicit current position
- structured review records grouped by function
- uploaded and chunked documents as future retrieval inputs
- findings, recommendations, reports, and government responses as distinct semantic layers

In practice, that should make it easier to add:

- step-specific drafting assistance
- document summarization and retrieval
- consultation and submission synthesis
- finding and recommendation generation support
- report drafting and response comparison workflows

# PLS Workflow Model

This document describes how the application models the post-legislative scrutiny (PLS) process.

## Core Review Record

The central record is `PlsReview`.

A review represents one PLS inquiry and stores:

- ownership via `created_by_user_id`
- optional organizational context via `review_group_id`
- broader institutional context through `legislature_id`, `jurisdiction_id`, and `country_id`
- review metadata such as `title`, `slug`, `description`, and `start_date`
- lifecycle state through `status`, `current_step_number`, and `completed_at`

A review is **owned by the user who creates it**.

Review status is modeled with three operational states:

- `draft`
- `active`
- `completed`

When a review is created, it starts as:

- `status = draft`
- `current_step_number = 1`
- `completed_at = null`

## Step Model

Each review has 11 `PlsReviewStep` records.

Each step stores:

- `step_number`
- `step_key`
- `status`
- `started_at`
- `completed_at`
- `notes`

Step titles are derived from the canonical workflow definition in `App\Domain\Reviews\Support\PlsReviewWorkflow`.

Step status is modeled as:

- `pending`
- `in_progress`
- `completed`
- `skipped`

A step is considered open if it is not terminal (`completed` or `skipped`).

## Canonical 11-Step Workflow

The system uses this ordered workflow:

1. Define the objectives and scope of PLS
2. Collect background information and prepare a data collection plan
3. Identify key stakeholders and prepare a consultation plan
4. Review implementing agencies and delegated legislation
5. Conduct consultation and public engagement activities
6. Analyse post-legislative scrutiny findings
7. Draft the PLS report
8. Disseminate the report and make it publicly accessible
9. Invite a response from the government to "comply or explain"
10. Conduct follow-up to the post-legislative scrutiny activities
11. Evaluate the post-legislative scrutiny inquiry results and process

Each step also has a stable key:

- `define_scope`
- `background_data_plan`
- `stakeholder_plan`
- `implementation_review`
- `consultations`
- `analysis`
- `draft_report`
- `dissemination`
- `government_response`
- `follow_up`
- `evaluation`

## Workflow Progression Rules

The application enforces a sequential workflow.

Only the first incomplete step can be started or completed.

That means:

- users cannot start a later step while an earlier step remains incomplete
- users cannot complete a later step while an earlier step remains incomplete
- `current_step_number` always points to the first incomplete step
- when all steps are complete, the review is marked `completed` and `current_step_number` remains at the final step

Operationally:

- starting the first incomplete step moves the review to `active`
- completing a step advances the review to the next incomplete step
- completing the last open step marks the review `completed`
- reopening a step moves that step back into progress, reopens the review, and resets later steps to `pending`

## Organizational Context (ReviewGroup)

Reviews may optionally be associated with a `ReviewGroup`.

A ReviewGroup is a flexible organizational concept that can represent:

- a review team
- an office
- a department
- a task force
- a research unit
- or another internal designation

This replaces the previous assumption that all reviews belong to a dedicated legacy grouping record.

A review is:

- **owned by a user**
- optionally **associated with a ReviewGroup**

## Institutional Context

In addition to ReviewGroup, a review may be linked to:

- `country`
- `jurisdiction`
- `legislature`

These are independent fields and are not derived from ReviewGroup.

When displaying assignment context in the UI, the system uses the following fallback order:

1. review group name
2. legislature name
3. jurisdiction name
4. `Unassigned`

This is presentation logic only. None of these relationships are strictly required.

## Review Workspace Data Model

A review acts as the container for the main PLS working records.

The workspace attaches these record types to a review:

- legislation
- legislation objectives
- documents
- document chunks
- evidence items
- stakeholders
- implementing agencies
- consultations
- submissions
- findings
- recommendations
- reports
- government responses

This lets the review workspace function as the operational hub for the inquiry.

## How the Workspace Maps to the Workflow

The UI is organized into tabs, but the intent is workflow-driven rather than CRUD-driven.

Workspace areas map roughly like this:

- `Workflow`
  - overall step status, step detail, and progression context
- `Legislation`
  - core legislation linked to the review
- `Documents`
  - source materials and uploaded files
- `Stakeholders`
  - stakeholder register and implementing agencies
- `Consultations`
  - consultation activity and submissions
- `Analysis`
  - findings and recommendations
- `Reports`
  - reports and government responses

## Step-Aware Guidance

The review workspace includes step-aware guidance.

That guidance does not change the workflow model itself. It is a UI layer that:

- identifies the current step
- points the user to the most relevant workspace tab
- suggests the most likely next action for that phase

## Document Handling

Review documents are first-class records attached to the review.

Each document stores metadata such as:

- title
- document type
- storage path
- MIME type
- file size
- summary

The workspace supports direct file uploads for review documents.

Documents may also be chunked into `document_chunks` as part of future retrieval and AI features.

## Reporting and Government Response

Reports are modeled separately from source documents.

A report can:

- belong to a review
- link to an optional document record
- carry a report type and publication status

Government responses are separate records linked to:

- the review
- a specific report
- an optional document

## Design Principles

The system is designed around a few core principles:

- Workflow-first: the 11-step PLS process is the backbone of the system
- Structured over free-form: key outputs are explicitly modeled
- Separation of concerns: evidence, analysis, and reporting are distinct layers
- Human-led, AI-assisted: the system supports users without replacing judgment

## Why This Structure Helps Future AI Features

The model provides strong anchors for AI features:

- a canonical workflow with explicit current position
- structured records grouped by function
- uploaded and chunked documents for retrieval
- findings, recommendations, and reports as semantic layers

This supports future capabilities such as:

- step-specific assistance
- document summarization and retrieval
- consultation synthesis
- recommendation generation
- report drafting and comparison
