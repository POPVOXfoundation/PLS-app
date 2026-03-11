# PLS Prototype Next-Phase Backlog

This backlog assumes the current prototype already includes:

- domain models, enums, migrations, factories, and demo seeders
- review creation with the seeded 11-step workflow
- initial reviews index, create, and show pages
- validation for the current create and attach flows
- baseline Pest coverage for the core workflow
- placeholder document chunking for future AI workflows

The next phase should prioritize workflow completion over more scaffolding.

## Must Do Next

### 1. Replace the starter dashboard with a real PLS dashboard

Build a true landing page for authenticated users that shows:

- active reviews by status
- reviews grouped by committee or legislature
- upcoming workflow attention items
- counts for legislation, documents, findings, recommendations, and reports

Acceptance target:

- users can understand review health without entering a review
- dashboard cards link to the reviews index or the relevant review workspace

### 2. Add workflow progression actions for the 11-step review lifecycle

The prototype can display steps but cannot yet run the workflow.

Deliver:

- actions to start, complete, and reopen individual review steps
- logic to keep `current_step_number` aligned with the active workflow state
- sensible status transitions for the review when work begins or completes
- tests for step progression, reopening, and completion behavior

Acceptance target:

- a review can move from draft into an active workflow and eventually to completed
- the current step indicator in the UI always reflects the persisted workflow state

### 3. Add real create and attach flows inside the review workspace

The show page currently summarizes data but does not support actual workflow entry.

Deliver:

- legislation attach form and legislation create form where needed
- document metadata create flow and file upload path
- finding create flow
- recommendation create flow tied to findings
- report create flow

Acceptance target:

- a user can complete the core review data-entry loop without leaving the review workspace
- each create flow uses the existing validators or dedicated form objects where cleaner

### 4. Build stakeholder management screens

Stakeholders are in the schema but not in the product.

Deliver:

- stakeholder list and create flow within a review
- stakeholder type filtering
- submission linkage from stakeholder records
- implementing agency management for the implementation-review step

Acceptance target:

- review teams can identify who must be consulted, who submitted evidence, and which agencies are responsible for implementation

### 5. Build consultations and submissions workflow

The prototype needs actual consultation tracking to support steps 3 through 5.

Deliver:

- consultation create and edit flow
- submission intake flow linked to stakeholders and documents
- consultation timeline or list view
- summary fields for hearing outcomes, public engagement, and submission volume

Acceptance target:

- consultations and submissions can be recorded as first-class workflow artifacts
- review pages clearly distinguish planned consultation work from completed consultation activity

### 6. Add report drafting and government response tracking

Steps 7 through 10 need real operational support.

Deliver:

- report drafting records with status and publication state
- report-document linkage and final report designation
- government response records with status, received date, and summary
- UI indicators for overdue or missing government responses

Acceptance target:

- a review can move from findings to report output to government response follow-up without external spreadsheets

### 7. Add baseline authorization and role boundaries

The current prototype assumes an authenticated user can do everything.

Deliver:

- policies or gates for reviews and related records
- initial roles such as admin, committee staff, reviewer, and read-only observer
- route and action authorization checks

Acceptance target:

- edit actions are blocked unless the user has an appropriate role for the review or institution

## Should Do Soon

### 8. Add follow-up action tracking and evaluation support

The later PLS steps need dedicated records instead of free-text notes.

Deliver:

- follow-up actions tied to recommendations or reports
- evaluation records for step 11
- due dates, owners, and completion states

### 9. Improve review workspace UX for professional use

The current workspace is functional but thin.

Deliver:

- empty states for each section
- loading and success states aligned with Flux patterns
- inline counts, filters, and section-level actions
- clearer workflow summaries and step-specific prompts

### 10. Add search, filtering, and sorting across the review corpus

Deliver:

- reviews index filters by country, jurisdiction, legislature, committee, and status
- document and legislation search within a review
- sorting for recent activity and step progress

### 11. Move ingestion and heavier workflows onto queued jobs

Deliver:

- queued document chunking
- queued file processing for uploads
- visible processing state in the UI

This keeps the UI responsive once real files replace demo metadata.

### 12. Refine terminology and presentation by legislature

Deliver:

- terminology overrides such as parliament versus assembly
- configurable labels for committee-facing language
- room for legislature-specific workflow notes without forking the product

## Later Enhancements

### 13. AI-assisted extraction of legislation objectives

Use the existing chunking foundation to propose candidate legislation objectives from uploaded source material.

Deliver:

- draft objective suggestions
- human review and approval flow
- source traceability back to document chunks

### 14. AI-assisted evidence summarization

Deliver:

- document summaries
- consultation summary drafts
- finding support summaries with citations to evidence

This should remain assistive, not automatic publishing.

### 15. AI-assisted consultation and submission synthesis

Deliver:

- clustering of recurring themes across submissions
- sentiment or support/opposition summaries where appropriate
- surfaced stakeholder gaps or underrepresented groups

### 16. Multi-language support

Deliver:

- locale-aware formatting and translations
- translatable review and reporting surfaces
- document metadata support across multiple languages

This matters once the product is used across multiple legislatures and countries.

### 17. Advanced reporting and export

Deliver:

- print-friendly report views
- export to PDF and structured data formats
- public-facing publication views for finalized reports and responses

## Suggested Delivery Order

1. real dashboard
2. workflow progression actions
3. review workspace create and attach flows
4. stakeholder management
5. consultations and submissions
6. report drafting and government response tracking
7. authorization
8. UX polish, search, and queued processing
9. AI and multi-language enhancements
