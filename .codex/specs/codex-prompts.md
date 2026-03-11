# Codex Prompts – PLS Laravel Prototype

Use these prompts in order. Each prompt assumes Codex has access to:

- `AGENTS.md`
- `pls-codex-build-spec.md`
- `codex-build-plan.md`

The goal is to keep Codex focused, incremental, and Laravel-native.

---

# Prompt 1 – Scaffold the domain foundation

Read `AGENTS.md`, `pls-codex-build-spec.md`, and `codex-build-plan.md`.

Scaffold the foundational domain structure for a Laravel 12 PLS application.

Requirements:
- Follow Laravel conventions
- Prefer domain-oriented organization if practical
- Use PHP 8.4 features where helpful
- Use native backed enums
- Do not build UI yet
- Do not invent extra product scope

Generate:
1. all PHP enums referenced in the plan
2. empty or initial Eloquent models for the core domain
3. relationship stubs on models
4. appropriate casts for enums and dates
5. factories for the main models

Focus only on:
- countries
- jurisdictions
- legislatures
- committees
- pls_reviews
- pls_review_steps
- legislation
- pls_review_legislation
- legislation_objectives
- documents
- document_chunks
- evidence_items
- stakeholders
- implementing_agencies
- consultations
- submissions
- findings
- recommendations
- reports
- government_responses

At the end, summarize:
- files created
- assumptions made
- anything intentionally deferred

---

# Prompt 2 – Generate migrations in correct dependency order

Read `codex-build-plan.md`.

Generate Laravel migrations for the PLS prototype in the correct dependency order.

Requirements:
- Use foreign keys correctly
- Use constrained relationships where appropriate
- use cascade or null-on-delete deliberately
- add indexes where clearly useful
- do not over-engineer
- do not add polymorphic tables
- do not add tables not specified in the build plan

Important:
- ensure migration order avoids circular dependency issues
- if `legislation_objectives.source_document_id` creates an ordering problem, place it in a later migration
- use slugs where specified
- use json columns only where the plan says json

After generating migrations:
1. verify they can run in order
2. identify any foreign key concerns
3. list any follow-up migration needed

---

# Prompt 3 – Build the review creation workflow

Read `pls-codex-build-spec.md` and `codex-build-plan.md`.

Implement the service/action layer for creating a new PLS review.

Requirements:
- creating a review must also create the 11 seeded review steps
- the step titles should match the official workflow
- the step keys should match the build plan
- set the initial review status to draft
- set current_step_number to 1 after seeding
- keep the logic in a dedicated action or service class
- add tests for this behavior using Pest

Generate:
1. a review creation action/service
2. any DTO or form object if useful
3. seed logic for the 11 steps
4. Pest feature tests and/or unit tests

Use clean Laravel code, not pseudo-code.

---

# Prompt 4 – Add model relationships and casts cleanly

Review all existing PLS domain models and complete their relationships.

Requirements:
- add belongsTo / hasMany / belongsToMany only where needed
- use enum casts for status/type fields
- use date/datetime casts appropriately
- avoid speculative abstractions
- do not add repository classes unless clearly necessary

Check these especially:
- Country -> Jurisdictions
- Jurisdiction -> Country, Legislatures, Legislation
- Legislature -> Jurisdiction, Committees, Reviews
- Committee -> Legislature, Reviews
- PlsReview -> Committee, Legislature, Jurisdiction, Country, Steps
- Legislation -> Jurisdiction, Reviews, Objectives
- Document -> Review, Legislation, Chunks
- Finding -> Review
- Recommendation -> Review, Finding
- Report -> Review
- GovernmentResponse -> Review, Report

After updating, provide a concise summary of all relationships implemented.

---

# Prompt 5 – Generate seeders for demo data

Create seeders for realistic demo data for the PLS prototype.

Requirements:
- include at least 2 countries
- include multiple jurisdictions
- include multiple legislatures
- include multiple committees
- include at least 2 PLS reviews
- include legislation records
- include seeded review steps
- include a few findings, recommendations, and reports
- keep the data realistic for parliamentary / legislative oversight use

Do not generate fake nonsense names unless needed.
Prefer plausible demo entities like:
- Belize
- Uganda
- United States
- Tennessee
- Parliament / National Assembly / General Assembly

At the end, explain how to run the seeders.

---

# Prompt 6 – Build initial Livewire screens for the MVP

Read the build plan and generate the first-pass Livewire UI for the PLS prototype.

Build only this MVP UI:

1. Reviews index page
2. Review create page/form
3. Review show page with step navigation
4. Step detail panel for the current step
5. Legislation section
6. Documents section
7. Findings and recommendations summary section

Requirements:
- use Livewire
- use Tailwind
- keep the UI clean and simple
- do not over-design
- optimize for workflow clarity
- show the 11-step process in the UI
- show current step status
- allow navigation between steps
- do not build every edit form in full if not necessary

Use labels appropriate for a professional parliamentary workflow tool.

After generating, explain the page/component structure.

---

# Prompt 7 – Add validation and form requests or equivalent

Review all create/update flows currently implemented and add proper validation.

Requirements:
- validate review creation
- validate legislation creation or attachment
- validate document metadata where relevant
- validate findings and recommendations inputs if present
- use Laravel-native validation
- keep rules practical and not overly strict

At the end, list all validation rules introduced.

---

# Prompt 8 – Add Pest tests for the core workflow

Create Pest tests for the current MVP.

Cover at least:
- review creation works
- 11 steps are seeded in correct order
- current_step_number is initialized correctly
- review belongs to committee / legislature / jurisdiction / country
- legislation can be linked to a review
- documents can be associated to a review
- findings can be associated to a review
- recommendations can be associated to findings
- reports can be associated to a review

Requirements:
- prefer feature tests for workflow behavior
- use factories
- keep test setup clean
- do not use brittle implementation-specific assertions unless necessary

At the end, list which behaviors remain untested.

---

# Prompt 9 – Add placeholders for AI-ready document ingestion

Add a minimal AI-ready document ingestion foundation without integrating an LLM yet.

Requirements:
- keep `documents` and `document_chunks` usable
- create a service/action that can accept raw document text and split it into chunks
- store chunk index and content
- leave embedding integration as a future placeholder
- do not call external APIs
- do not overcomplicate chunking logic

Generate:
1. a document chunking action/service
2. a simple chunking strategy
3. tests for chunk generation
4. comments or TODOs where embeddings would later be added

Keep this implementation local and deterministic.

---

# Prompt 10 – Refactor for cleanliness without changing scope

Review everything generated so far and refactor for cleanliness.

Requirements:
- improve naming consistency
- remove duplicated logic
- keep Laravel conventions strong
- do not expand scope
- do not add unnecessary patterns
- do not rewrite working code just to be clever

Focus on:
- enum usage
- action/service boundaries
- model organization
- Livewire component naming
- test readability

At the end, provide:
1. a concise refactor summary
2. anything still rough
3. recommended next build step

---

# Prompt 11 – Generate the next-phase backlog

Based on the existing PLS prototype, generate a markdown backlog for the next phase.

Organize by:
- Must do next
- Should do soon
- Later enhancements

Include likely next-phase items such as:
- stakeholder management screens
- consultations workflow
- report drafting workflow
- government response tracking
- follow-up actions
- evaluation step
- AI-assisted extraction of legislation objectives
- AI-assisted evidence summarization
- role/permission refinement
- multi-language support
- terminology overrides by legislature

Write the backlog as a practical product-engineering plan, not vague ideas.

---

# Best practice note for using these prompts

Use the prompts one at a time. After each one:

1. review the diff
2. run tests
3. fix issues
4. then move to the next prompt

Do not ask Codex to build the entire application in one shot.
