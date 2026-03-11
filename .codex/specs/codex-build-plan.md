# Codex Build Plan – PLS Laravel Prototype

## Goal

Build a Laravel 12 application for the full Post-Legislative Scrutiny (PLS) process.

Requirements:

* Multi-country
* Multi-jurisdiction (federal, state, province, etc.)
* Multiple legislatures and committees
* Implements the official 11-step WFD PLS workflow

Stack:

* Laravel 12
* PHP 8.4
* Livewire
* Tailwind
* Pest

---

# 1. Build Order

Codex should execute development in this order:

1. Enums
2. Migrations
3. Eloquent models
4. Factories
5. Seeders
6. Services / Actions
7. Livewire components
8. Pest tests
9. Dashboard UI
10. Document ingestion placeholders

---

# 2. Domain Folder Structure

```
app/
  Domain/
    Institutions/
    Reviews/
    Legislation/
    Documents/
    Stakeholders/
    Consultations/
    Analysis/
    Reporting/
```

---

# 3. Enums

Create PHP enums for:

* JurisdictionType
* LegislatureType
* PlsReviewStatus
* PlsStepStatus
* DocumentType
* EvidenceType
* StakeholderType
* ConsultationType
* FindingType
* RecommendationType
* ReportStatus
* GovernmentResponseStatus

Example:

```php
enum PlsReviewStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';
}
```

---

# 4. Core Institutional Tables

## countries

* id
* name
* iso2
* iso3
* default_locale
* timestamps

---

## jurisdictions

* id
* country_id
* name
* slug
* jurisdiction_type
* parent_id nullable
* timestamps

---

## legislatures

* id
* jurisdiction_id
* name
* slug
* legislature_type
* description nullable
* timestamps

---

## committees

* id
* legislature_id
* name
* slug
* description nullable
* timestamps

---

# 5. PLS Review Tables

## pls_reviews

* id
* committee_id
* legislature_id
* jurisdiction_id
* country_id
* title
* slug
* description
* status
* current_step_number
* start_date
* completed_at
* timestamps

---

## pls_review_steps

* id
* pls_review_id
* step_number
* step_key
* status
* started_at
* completed_at
* notes
* timestamps

Seed the following steps automatically:

1 define_scope
2 background_data_plan
3 stakeholder_plan
4 implementation_review
5 consultations
6 analysis
7 draft_report
8 dissemination
9 government_response
10 follow_up
11 evaluation

---

# 6. Legislation Tables

## legislation

* id
* jurisdiction_id
* title
* short_title
* legislation_type
* date_enacted
* summary
* timestamps

---

## pls_review_legislation

* id
* pls_review_id
* legislation_id
* relationship_type
* timestamps

---

## legislation_objectives

* id
* legislation_id
* pls_review_id
* title
* description
* timestamps

---

# 7. Documents

## documents

* id
* pls_review_id
* title
* document_type
* storage_path
* mime_type
* file_size
* summary
* metadata json
* timestamps

---

## document_chunks

* id
* document_id
* chunk_index
* content
* token_count
* embedding nullable
* metadata json
* timestamps

---

# 8. Evidence

## evidence_items

* id
* pls_review_id
* document_id
* title
* evidence_type
* description
* timestamps

---

# 9. Stakeholders

## stakeholders

* id
* pls_review_id
* name
* stakeholder_type
* contact_details json
* timestamps

---

## implementing_agencies

* id
* pls_review_id
* name
* agency_type
* timestamps

---

# 10. Consultations

## consultations

* id
* pls_review_id
* title
* consultation_type
* held_at
* summary
* document_id
* timestamps

---

## submissions

* id
* pls_review_id
* stakeholder_id
* document_id
* submitted_at
* summary
* timestamps

---

# 11. Analysis

## findings

* id
* pls_review_id
* title
* finding_type
* summary
* detail
* timestamps

---

## recommendations

* id
* pls_review_id
* finding_id
* title
* description
* recommendation_type
* timestamps

---

# 12. Reporting

## reports

* id
* pls_review_id
* title
* report_type
* status
* document_id
* published_at
* timestamps

---

## government_responses

* id
* pls_review_id
* report_id
* document_id
* response_status
* received_at
* timestamps

---

# 13. UI Structure

Dashboard
PLS Reviews

Inside a review:

* Overview
* Scope
* Background Research
* Stakeholders
* Implementation Review
* Consultations
* Analysis
* Draft Report
* Publish
* Government Response
* Follow-up
* Evaluation

---

# 14. Testing

Use Pest.

Tests should cover:

* review creation
* step progression
* document ingestion
* evidence linking
* report creation

---
