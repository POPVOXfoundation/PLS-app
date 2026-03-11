# PLS Laravel Prototype – Codex Build Specification

## Purpose
Build a Laravel application that supports the full Post-Legislative Scrutiny (PLS) workflow used by parliaments and legislatures.

The system must support:
- multiple countries
- multiple jurisdiction levels
- multiple legislatures and committees

Stack assumptions:
- Laravel 12
- PHP 8.4
- Livewire
- Tailwind
- Pest for testing

---

# 1. Institutional Context Model

Hierarchy:

Country
→ Jurisdiction
→ Legislature
→ Committee
→ PLS Review

## Tables

### countries

id  
name  
iso2  
iso3  
default_locale  
timestamps

---

### jurisdictions

id  
country_id  
name  
slug  
jurisdiction_type  
parent_id nullable  
timestamps

Examples of jurisdiction_type:

- national
- federal
- state
- province
- territory
- region
- municipal

---

### legislatures

id  
jurisdiction_id  
name  
slug  
legislature_type  
description  
timestamps

Examples:

- Parliament
- Congress
- Assembly
- Legislature

---

### committees

id  
legislature_id  
name  
slug  
description  
timestamps

---

# 2. PLS Review Workflow

PLS Reviews must follow the official WFD process.

## The 11 Steps

1. Define the objectives and scope of PLS
2. Collect background information and prepare a data collection plan
3. Identify key stakeholders and prepare a consultation plan
4. Review implementing agencies and delegated legislation
5. Conduct consultation and public engagement activities
6. Analyse post-legislative scrutiny findings
7. Draft the PLS report
8. Disseminate the report and make it publicly accessible
9. Invite a response from the government to “comply or explain”
10. Conduct follow-up to the post-legislative scrutiny activities
11. Evaluate the post-legislative scrutiny inquiry results and process

---

# 3. Core Review Tables

### pls_reviews

id  
committee_id  
legislature_id  
jurisdiction_id  
country_id  
title  
slug  
description  
status  
current_step_number  
start_date  
completed_at  
timestamps

Status values:

- draft
- active
- completed
- archived

---

### pls_review_steps

id  
pls_review_id  
step_number  
step_key  
status  
started_at  
completed_at  
notes  
timestamps

Seed steps automatically when a review is created.

Step keys:

define_scope  
background_and_data_plan  
stakeholder_plan  
implementation_review  
consultation  
analysis  
draft_report  
dissemination  
government_response  
follow_up  
evaluation

---

# 4. Legislation Model

### legislation

id  
jurisdiction_id  
title  
short_title  
legislation_type  
date_enacted  
summary  
timestamps

Examples of legislation_type:

- act
- law
- statute
- regulation
- ordinance

---

### pls_review_legislation

id  
pls_review_id  
legislation_id  
relationship_type  
timestamps

Relationship types:

- primary
- related
- delegated

---

### legislation_objectives

id  
legislation_id  
pls_review_id  
title  
description  
timestamps

These represent the intended policy objectives of the law.

---

# 5. Documents and Evidence

### documents

id  
pls_review_id  
title  
document_type  
storage_path  
mime_type  
file_size  
summary  
metadata json  
timestamps

Examples of document_type:

- legislation_text
- committee_report
- implementation_report
- consultation_submission
- hearing_transcript
- policy_report
- government_response
- draft_report
- final_report

---

### document_chunks

id  
document_id  
chunk_index  
content  
token_count  
embedding nullable  
metadata json  
timestamps

Used for AI retrieval and analysis.

---

### evidence_items

id  
pls_review_id  
document_id  
title  
evidence_type  
description  
timestamps

Examples of evidence_type:

- documentary
- statistical
- testimony
- consultation
- analysis

---

# 6. Stakeholders and Agencies

### stakeholders

id  
pls_review_id  
name  
stakeholder_type  
contact_details json  
timestamps

Examples:

- ministry
- government agency
- NGO
- academic
- expert
- industry group
- citizen group

---

### implementing_agencies

id  
pls_review_id  
name  
agency_type  
timestamps

These are agencies responsible for implementing the legislation.

---

# 7. Consultations

### consultations

id  
pls_review_id  
title  
consultation_type  
held_at  
summary  
document_id  
timestamps

Examples:

- hearing
- roundtable
- interview
- public consultation
- workshop

---

### submissions

id  
pls_review_id  
stakeholder_id  
document_id  
submitted_at  
summary  
timestamps

Represents written submissions from stakeholders.

---

# 8. Analysis

### findings

id  
pls_review_id  
title  
finding_type  
summary  
detail  
timestamps

Examples of finding_type:

- implementation_gap
- effectiveness_issue
- unintended_consequence
- compliance_problem
- administrative_issue

---

### recommendations

id  
pls_review_id  
finding_id  
title  
description  
recommendation_type  
timestamps

Examples:

- amend_legislation
- improve_implementation
- oversight_action
- request_more_data

---

# 9. Reporting

### reports

id  
pls_review_id  
title  
report_type  
status  
document_id  
published_at  
timestamps

Examples of report_type:

- draft_report
- final_report
- briefing_note
- public_summary

---

### government_responses

id  
pls_review_id  
report_id  
document_id  
response_status  
received_at  
timestamps

Examples of response_status:

- requested
- received
- overdue

---

# 10. Enums to Implement

JurisdictionType  
LegislatureType  
PlsReviewStatus  
PlsStepStatus  
DocumentType  
EvidenceType  
StakeholderType  
ConsultationType  
FindingType  
RecommendationType  
ReportStatus

---

# 11. Build Order

Codex should build the system in this order:

1. Institutional tables
2. PLS review workflow
3. Legislation models
4. Documents and chunking
5. Evidence
6. Stakeholders
7. Consultations
8. Findings and recommendations
9. Reports and responses

---

# 12. Expected UI Structure

Dashboard  
PLS Reviews  
Review Workspace

Each review should have tabs:

Overview  
Scope  
Background Research  
Stakeholders  
Implementation Review  
Consultations  
Analysis  
Draft Report  
Publish  
Government Response  
Follow-up  
Evaluation

---

# 13. Testing

Use Pest.

Tests should cover:

- Review creation
- Step progression
- Document ingestion
- Evidence linking
- Report generation

---

End of specification.
