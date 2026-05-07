# PLS Bot Prototype

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

### Requirements

- PHP 8.2+
- Composer
- Node.js 20.19+ or 22.12+
- npm
- A supported database
  - SQLite for local development
  - MySQL or PostgreSQL recommended for production
- Redis, if using Laravel Horizon in production
- An OpenAI API key for AI assistant functionality
- AWS credentials, if using S3 document storage or AWS Textract-backed document extraction
- Composer authentication for Flux Pro packages, if installing dependencies from the private Flux repository

### Local Development Setup

Clone the repository and install dependencies:

```bash
git clone https://github.com/POPVOXfoundation/PLS-app.git
cd PLS-app

composer install
npm install
```

Create the local environment file and application key:

```bash
cp .env.example .env
php artisan key:generate
```

For SQLite development, create the local database file if it does not already exist:

```bash
touch database/database.sqlite
```

Edit `.env` for your local environment. At minimum, confirm:

```env
APP_NAME="PLS Bot Prototype"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=sqlite

QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

OPENAI_API_KEY=
OPENAI_URL=https://api.openai.com/v1
```

Run database migrations and build frontend assets:

```bash
php artisan migrate
npm run build
```

Start the local development environment:

```bash
composer run dev
```

The `composer run dev` command starts the Laravel development server, Vite, queue listener, and log tailing process together. Alternatively, these processes can be run separately:

```bash
php artisan serve
npm run dev
php artisan queue:listen --tries=1 --timeout=0
```

### Production Deployment

Production deployment should be managed by the hosting operator using the hosting provider's normal release process. A typical Laravel deployment includes:

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build

php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Ensure the web server points to the `public/` directory and that `storage/` and `bootstrap/cache/` are writable by the application user.

If local public storage is used, create the Laravel storage symlink:

```bash
php artisan storage:link
```

If the deployment uses database queues, run a supervised queue worker:

```bash
php artisan queue:work --tries=1 --timeout=240
```

If the deployment uses Redis queues and Laravel Horizon, set `QUEUE_CONNECTION=redis`, configure Redis, and run Horizon under a process supervisor:

```bash
php artisan horizon
```

After each deployment, restart queue workers so they load the new code:

```bash
php artisan queue:restart
```

For Horizon deployments, terminate Horizon so the process supervisor starts it again with the new code:

```bash
php artisan horizon:terminate
```

### Current Hosting

The prototype is currently hosted and operated by POPVOX Foundation at [https://pls.parllink.com](https://pls.parllink.com). POPVOX manages infrastructure, credentials, and ongoing operations for this phase of the project.

---

## Runbook

### Routine Operations

- Confirm the application responds at the configured `APP_URL`.
- Monitor application logs in `storage/logs/` or the configured production log service.
- Monitor failed queue jobs with:

```bash
php artisan queue:failed
```

- Retry failed jobs, when appropriate:

```bash
php artisan queue:retry all
```

- Clear failed jobs after investigation:

```bash
php artisan queue:flush
```

- For Horizon deployments, monitor the Horizon dashboard at the configured `HORIZON_PATH`, which defaults to `/horizon`.

### Common Maintenance Commands

Clear and rebuild cached configuration:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Put the application into maintenance mode:

```bash
php artisan down
```

Return the application to service:

```bash
php artisan up
```

Run tests:

```bash
composer test
```

Run code style checks:

```bash
composer lint:check
```

Apply code style fixes:

```bash
composer lint
```

### Document Processing

Uploaded review documents and assistant source documents are processed through queued jobs. The default queue names include:

- `default`
- `assistant-sources`
- `review-source-enrichment`

If using Horizon, these queues are configured in `config/horizon.php`. If using database queues, ensure workers are configured to process all required queues, for example:

```bash
php artisan queue:work --queue=default,assistant-sources,review-source-enrichment --tries=1 --timeout=240
```

The application can extract document text using local tooling or AWS Textract, depending on configuration. Local extraction expects a `pdftotext` binary to be available. AWS Textract extraction requires AWS configuration and the relevant Textract environment variables.

---

## Configuration

Configuration is managed through Laravel environment variables. Start from `.env.example` and set deployment-specific values in `.env` or in the hosting provider's secret manager.

### Core Application

```env
APP_NAME=
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=
APP_LOCALE=en
APP_FALLBACK_LOCALE=en
```

`APP_KEY` is required for encrypted application data. Generate it with:

```bash
php artisan key:generate
```

Do not change `APP_KEY` for an existing production deployment unless you understand the impact on encrypted data and sessions. `APP_DEBUG` should be `false` in production.

### Database

Local development defaults to SQLite:

```env
DB_CONNECTION=sqlite
```

Production should use a managed database where possible:

```env
DB_CONNECTION=mysql
DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

or:

```env
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

Database credentials should be regenerated through the hosting provider or database provider. After rotation, update the deployment environment and restart the application and queue workers.

### Queues

The default queue configuration uses the database driver:

```env
QUEUE_CONNECTION=database
```

For Horizon-based production deployments, use Redis:

```env
QUEUE_CONNECTION=redis
REDIS_HOST=
REDIS_PORT=6379
REDIS_PASSWORD=
```

Optional Horizon configuration:

```env
HORIZON_NAME=
HORIZON_DOMAIN=
HORIZON_PATH=horizon
HORIZON_PREFIX=
PLS_REVIEW_SOURCE_ENRICHMENT_QUEUE=review-source-enrichment
```

### Cache and Sessions

The default `.env.example` stores cache and sessions in the database:

```env
CACHE_STORE=database
SESSION_DRIVER=database
```

Production may use Redis or another supported Laravel cache/session backend if configured by the hosting operator.

### Files and Document Storage

Local storage:

```env
FILESYSTEM_DISK=local
```

S3-compatible storage:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=false
```

Regenerate AWS credentials in AWS IAM or the relevant S3-compatible provider. Use least-privilege credentials scoped to the required bucket and operations. After rotation, update the hosting secrets and restart the application and queue workers.

### AI Provider

The application uses Laravel AI and defaults to OpenAI for text generation:

```env
OPENAI_API_KEY=
OPENAI_URL=https://api.openai.com/v1
```

Regenerate OpenAI credentials from the OpenAI dashboard. Store the key only in local `.env` files or the production secret manager. Do not commit API keys to the repository.

The AI configuration also supports additional providers in `config/ai.php`, including Anthropic, Azure OpenAI, Cohere, Gemini, OpenRouter, and others. Only configure providers that are actively used by the deployment.

### PLS Assistant Source Configuration

Assistant source documents and review document enrichment use these optional settings:

```env
PLS_ASSISTANT_SOURCE_DISK=
PLS_ASSISTANT_SOURCE_PREFIX=assistant-sources/wfd
PLS_ASSISTANT_SOURCE_EXTRACTOR=local
PLS_ASSISTANT_PDFTOTEXT_BINARY=pdftotext
```

For AWS Textract-backed extraction:

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

Optional bootstrap paths for WFD reference materials:

```env
PLS_ASSISTANT_WFD_GUIDE_2017_PATH=
PLS_ASSISTANT_WFD_MANUAL_2023_PATH=
```

Import WFD assistant source documents with:

```bash
php artisan pls:assistant-sources:import-wfd
```

### Mail

Development defaults to log-based mail:

```env
MAIL_MAILER=log
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME="${APP_NAME}"
```

Configure SMTP, Postmark, SES, Resend, or another Laravel-supported mail driver for production if user invitations or email notifications are enabled.

### Composer Authentication

This project depends on Flux Pro packages from a private Composer repository. Deployment environments must be able to authenticate with `https://composer.fluxui.dev`.

Configure Composer credentials using the hosting provider's secret manager or Composer's supported authentication mechanisms, such as `COMPOSER_AUTH` or `composer config http-basic`.

Regenerate Flux credentials through the Flux account or organization that owns the subscription. After rotation, update deployment secrets before running `composer install`.

---

## Hosting Setup

The application can be hosted on any infrastructure that supports Laravel. A production hosting setup should include:

- A PHP application runtime serving the Laravel `public/` directory
- A production database
- A queue worker process
- Redis, if using Horizon
- Object storage, if using S3-backed document storage
- Secure secret management for API keys and credentials
- HTTPS termination
- Scheduled backups for the database and uploaded documents
- Log collection and error monitoring

Recommended supervised processes:

- Web process: PHP-FPM, Laravel Octane, or the hosting provider's Laravel runtime
- Queue process: `php artisan queue:work` or `php artisan horizon`
- Scheduler process, if scheduled commands are added:

```bash
php artisan schedule:work
```


---

## Status

This project is an **active prototype**. Features, models, and interfaces may change rapidly as the PLS workflow and AI capabilities are refined.

The system should be considered experimental and intended for demonstration and collaborative design purposes.

---

## License

This repository contains the source code for the PLS Bot Prototype developed by POPVOX Foundation for Westminster Foundation for Democracy. Use, distribution, modification, hosting, and maintenance of this software are governed by the contract between the parties. Third-party dependencies are licensed under their respective terms.
