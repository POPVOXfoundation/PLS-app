# PLS Bot Prototype
![Build Status](https://img.shields.io/endpoint?url=https://app.chipperci.com/projects/eb59b92e-aa7f-4a5d-a79b-e21c8f530a9b/status/main)

## Overview

This repository contains the prototype implementation of the **AI‑powered Post‑Legislative Scrutiny (PLS) Bot** being developed by **POPVOX Foundation** in collaboration with **Westminster Foundation for Democracy (WFD)** and **POPVOX Inc.**

The PLS Bot is a practical digital assistant designed to help parliamentary staff and legislative researchers conduct post‑legislative scrutiny exercises more efficiently and with greater confidence.

The system supports PLS work by helping users:

- Move through a structured **11‑step PLS workflow**
- Upload and analyze **legislation and supporting documents**
- Map **stakeholders** and track **consultation activity**
- Organize evidence into **findings and recommendations**
- Draft and manage **review reports**
- Interact with a **tab‑aware AI assistant** grounded in uploaded documents, jurisdiction‑specific guidance, and WFD methodology

The goal of this prototype is to demonstrate how AI can support structured oversight of legislation while keeping human experts firmly in control of the process.

---

## Project Directive

POPVOX Foundation is engaged to design and deliver a functional prototype of the AI powered Post‑Legislative Scrutiny (PLS) Bot in collaboration with WFD staff and POPVOX Inc. The PLS Bot is a practical digital assistant intended to help parliamentary staff and researchers structure post‑legislative scrutiny exercises faster and more confidently, guided by a structured workflow and supported by an AI assistant that adapts to each phase of the review.

---

## What This Prototype Demonstrates

This application is not intended to replace parliamentary judgement. Instead, it provides tools that assist staff with organizing and structuring review processes.

The prototype includes:

- **Review workspaces** organized into tabs: Workflow, Collaborators, Legislation, Documents, Stakeholders, Consultations, Analysis, and Reports
- **Membership‑based access control** with Owner, Contributor, and Viewer roles
- **Document processing** with text extraction (pdftotext / AWS Textract) and chunking for AI retrieval
- **Tab‑aware AI assistant** with per‑tab playbooks, a pre‑LLM refusal guard, and a three‑layer grounding model (global WFD methodology, jurisdiction guidance, review documents)
- **Configurable AI behavior** through a database‑driven playbook system with version tracking
- **Feedback collection** via an integrated widget for bug reports, improvement suggestions, and general feedback

---

## Technology Stack

This prototype is built using the Laravel ecosystem and modern server‑driven UI tools.

Core components:

- **Laravel 13** – application framework
- **Livewire 4** – reactive UI without a JavaScript SPA
- **Flux UI** – component library used for interface elements
- **Tailwind CSS 4** – styling system
- **Laravel AI** – LLM integration for the AI assistant
- **Laravel Horizon** - optional queue monitoring and worker management for Redis-backed production queues


---

## Documentation

Additional documentation is available in the `docs/` directory:

- **[Domain Model](docs/pls-domain-model.md)** – core entities, relationships, and data architecture
- **[Workflow Model](docs/pls-workflow.md)** – the 11‑step PLS process and how it is modeled
- **[AI Behavior & Governance](docs/pls_bot_ai_behavior_governance_framework_for_wfd.md)** – how the AI assistant behaves, its boundaries, and the governance framework

---

## Deployment

This application should be deployed using the hosting provider's normal Laravel release pipeline. The only project-specific deployment concerns are listed here.

### Services

- Database: PostgreSQL for shared environments.
- Queue: Redis plus Horizon is preferred for production.
- Storage: use S3-compatible storage for uploaded documents when releases are ephemeral or multiple app servers are used.
- Composer auth: Flux Pro dependencies require access to `https://composer.fluxui.dev`.
- AI provider: OpenAI credentials are required for assistant features.
- Document extraction: local `pdftotext` is enough for basic PDF extraction; AWS Textract requires AWS credentials and the Textract env vars in `config/pls_assistant.php`.

### Queues

Document ingestion and assistant source processing use these queues:

- `default`
- `assistant-sources`
- `review-source-enrichment`

Horizon is already configured for these queues in `config/horizon.php`.

```bash
php artisan horizon
```

If a deployment uses plain queue workers instead of Horizon, the worker must listen to all app queues:

```bash
php artisan queue:work --queue=default,assistant-sources,review-source-enrichment --tries=1 --timeout=240
```

After every release, restart long-running workers:

```bash
php artisan horizon:terminate
```

Use `php artisan queue:restart` for non-Horizon workers.

### App-Specific Environment

The standard Laravel env values should come from the deployment platform. These are the project-specific values worth calling out:

```env
OPENAI_API_KEY=
OPENAI_URL=https://api.openai.com/v1

PLS_ASSISTANT_SOURCE_DISK=
PLS_ASSISTANT_SOURCE_PREFIX=assistant-sources/wfd
PLS_ASSISTANT_SOURCE_EXTRACTOR=local
PLS_ASSISTANT_PDFTOTEXT_BINARY=pdftotext
PLS_REVIEW_SOURCE_ENRICHMENT_QUEUE=review-source-enrichment
```

For Textract-backed extraction:

```env
PLS_ASSISTANT_SOURCE_EXTRACTOR=textract
PLS_ASSISTANT_TEXTRACT_REGION=
PLS_ASSISTANT_TEXTRACT_BUCKET=
PLS_ASSISTANT_TEXTRACT_ROLE_ARN=
PLS_ASSISTANT_TEXTRACT_SNS_TOPIC_ARN=
PLS_ASSISTANT_TEXTRACT_ENDPOINT=
PLS_ASSISTANT_TEXTRACT_USE_PATH_STYLE_ENDPOINT=false
PLS_ASSISTANT_TEXTRACT_POLL_DELAY_SECONDS=15
PLS_ASSISTANT_TEXTRACT_MAX_POLL_ATTEMPTS=20
```

### Assistant Source Imports

The WFD reference PDFs can be imported with:

```bash
php artisan pls:assistant-sources:import-wfd
```

Set these when importing from explicit local paths:

```env
PLS_ASSISTANT_WFD_GUIDE_2017_PATH=
PLS_ASSISTANT_WFD_MANUAL_2023_PATH=
```

### Current Hosting

The prototype is currently hosted and operated by POPVOX Foundation at [https://pls.parllink.com](https://pls.parllink.com).

---

## Status

This project is an **active prototype**. Features, models, and interfaces may change rapidly as the PLS workflow and AI capabilities are refined.

The system should be considered experimental and intended for demonstration and collaborative design purposes.

---

## License

This repository contains the source code for the PLS Bot Prototype developed by POPVOX Foundation for Westminster Foundation for Democracy. Use, distribution, modification, hosting, and maintenance of this software are governed by the contract between the parties. Third-party dependencies are licensed under their respective terms.
