# Codex Start Instructions – PLS Prototype

You are working inside an existing Laravel 12 project.

The following specification files define the architecture:

- codex-build-plan.md
- codex-prompts.md
- pls-codex-build-spec.md
- AGENTS.md

Read all of them before writing any code.

Follow the prompts in codex-prompts.md sequentially.

---

# 1. Read the project guidance

Before writing any code, read:

1. AGENTS.md
2. pls-codex-build-spec.md
3. codex-build-plan.md
4. codex-prompts.md

Use them as the authoritative design for the system.

---

# 2. Do NOT try to build everything at once

Follow the prompts sequentially.

Start with:

Prompt 1 from `codex-prompts.md`

After completing each prompt:

* summarize the changes
* confirm migrations compile
* confirm tests pass
* wait for approval before continuing

---

# 3. Respect Laravel conventions

Use:

* Laravel 12 conventions
* Eloquent models
* native PHP enums
* Pest tests
* Livewire components
* Tailwind for UI

Avoid:

* repository patterns unless necessary
* unnecessary abstractions
* speculative architecture

---

# 4. Scope boundaries

Only implement what is described in the specification.

Do NOT add:

* new product features
* unrelated services
* external integrations
* authentication changes
* infrastructure configuration

The goal is a **clean PLS workflow prototype**.

---

# 5. First task

Execute **Prompt 1** from `codex-prompts.md`.

Generate:

* enums
* core models
* relationships
* factories

Do NOT generate migrations yet.

Wait for approval before continuing.
