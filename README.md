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

---

## Documentation

Additional documentation is available in the `docs/` directory:

- **[Domain Model](docs/pls-domain-model.md)** – core entities, relationships, and data architecture
- **[Workflow Model](docs/pls-workflow.md)** – the 11‑step PLS process and how it is modeled
- **[AI Behavior & Governance](docs/pls_bot_ai_behavior_governance_framework_for_wfd.md)** – how the AI assistant behaves, its boundaries, and the governance framework

---

## Status

This project is an **active prototype**. Features, models, and interfaces may change rapidly as the PLS workflow and AI capabilities are refined.

The system should be considered experimental and intended for demonstration and collaborative design purposes.

---

## License

This repository is part of a collaborative prototype effort between POPVOX Foundation, WFD, and POPVOX Inc.

Licensing and distribution terms will be finalized as the project progresses.
