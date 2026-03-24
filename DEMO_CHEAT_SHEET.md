# Demo Cheat Sheet: Production PLS Portfolio App

This cheat sheet is for the current production demo at `https://pls.parllink.com`.

Use your production account to log in.

The strongest walkthrough is the Belize review, `Post-Legislative Review of the Access to Information Act`, because it touches nearly every major workspace section.

## Demo Caveat

All four production reviews are still in `Draft` at step 1 of 11. That means the workflow tracker presents them as very early-stage reviews, even though some workspaces already include deeper materials like findings, reports, and a government response.

For the demo, describe this as a populated prototype workspace where some review records already contain representative downstream artifacts before the workflow status has been advanced.

## Suggested Demo Path

1. Log in and open the `Dashboard`.
2. Open `PLS Reviews`.
3. Open the Belize review.
4. Walk tab-by-tab through the review workspace.
5. End on `Reports` to show the full arc from legislation to follow-up.

## Dashboard

This is the portfolio overview for all accessible PLS reviews on production.

What to say:

- It gives the team a portfolio-level view rather than a single-review view.
- The top cards summarize how many reviews exist, how many are active, and how many need attention.
- `Workflow pipeline` shows where reviews are clustered across the scrutiny lifecycle.
- `Needs attention` highlights reviews that are draft, stalled, or otherwise need movement.
- `Recent reviews` is a quick entry point into the latest work.
- `Assignment workload` shows which review groups or institutional owners are carrying the portfolio.

Current production state:

- There are 4 total reviews.
- `Active` is 0.
- `Needs attention` is 4, because all current reviews are still drafts.
- All reviews currently sit in the `Scoping` phase on the dashboard, even though some have richer downstream content attached.

## PLS Reviews Index

This is the portfolio list of individual scrutiny reviews.

What to say:

- Each card represents one review workspace.
- It shows the review title, institutional assignment, current workflow step, progress percentage, and quick counts for core review materials.
- The quick counts give a fast sense of how populated a workspace is before opening it.
- This page is where a user decides which review to enter or whether to create a new one.

Current production examples:

- Belize: richest example, with legislation, 5 documents, 1 finding, and 1 recommendation.
- Uganda: similar mid-to-late workspace content, with legislation, documents, analysis, a report, and a government response.
- Tennessee: lighter early-stage example, with legislation, one document, one stakeholder, and one consultation, but no findings or reports yet.
- `S.1177 - Every Student Succeeds Act`: early-stage federal example with 1 legislation record, 6 stakeholders, and no documents, consultations, findings, or reports yet.

## Create Review

This page creates a new review shell.

What to say:

- A new review starts with minimal metadata: legislature, optional review group, title, optional start date, and description.
- The legislature is the main institutional anchor.
- The review group is optional and adds organizational context, not permissions.
- The right-hand preview helps the user confirm the institutional context before saving.
- The page also previews the canonical 11-step PLS workflow that will be created automatically.

Important behavior:

- New reviews are created in `Draft`.
- Step 1 is selected automatically.
- All 11 workflow steps are created automatically at review creation time.

## Review Workspace Header

The review header is the summary and orientation area for a single inquiry.

What to say:

- The title and badge show what review this is and what state it is in.
- The line under the title shows the institutional assignment, legislature, jurisdiction, and country context.
- The short description explains the goal of the inquiry.
- The progress bar shows where the review sits in the 11-step lifecycle.
- The `Current workspace focus` card is a guidance layer that points the user to the most relevant tab for the current step.

Belize example:

- The review is still `Draft`, step 1 of 11.
- The focus card points the team to `Legislation and documents`.
- The recommended next action is to link the law and add initial working materials.

## Workflow Tab

This is the canonical 11-step PLS process tracker.

What to say:

- The left side is the full lifecycle of a PLS review, from scope definition through evaluation.
- The selected step on the right explains what that stage is for.
- The metric cards summarize what artifacts already exist for that stage.
- This tab is mainly for orientation and process discipline: it helps the team understand what kind of work should happen now.

Why it matters:

- It anchors the rest of the workspace to a formal scrutiny process.
- It helps explain why the other tabs exist and when they become relevant.

Belize example:

- Step 1 is selected.
- The step detail shows that legislation, objectives, and working documents already exist.

## Collaborators Tab

This is the access-control and team membership area for a review.

What to say:

- Access is explicit and invitation-based.
- Review group assignment does not automatically grant access.
- `Owner` can edit the workspace and manage access.
- `Editor` can work in the workspace but cannot manage collaborator permissions.
- This tab separates institutional context from actual permissions.

Current production state:

- Access is membership-based rather than inherited from review-group assignment.
- The Belize review currently shows `bryan@popvox.com` as an `editor`.
- That means the collaborators area is useful in the demo as a permissions model, but the live production data is still sparse.

## Legislation Tab

This is where the governing law under review is attached or created.

What to say:

- The review can link to an existing legislation record or create a new one in place.
- This tab stores the core law, relationship type, enactment date, and high-level metadata.
- It defines what legal instrument the review is actually scrutinizing.

Belize example:

- The review is linked to the `Access to Information Act`.
- The relationship is `Primary`.
- The legislation type is `Act`.
- The record includes the short title `ATI Act` and enactment date.

## Documents Tab

This is the review’s source-material repository.

What to say:

- This tab is the document spine of the workspace.
- It stores the texts and files the review relies on, not just final outputs.
- It can hold legislation text, implementation reports, consultation submissions, final reports, and government responses.
- In practice, this is where the review team builds the documentary record behind the inquiry.

Belize example documents:

- `Access to Information Act`
- `Ministry Implementation Progress Report`
- `Belize Transparency Initiative Submission`
- `Final PLS Report on the Access to Information Act`
- `Government Response to the ATI Act Review`

## Stakeholders Tab

This is the people-and-institutions map for the review.

What to say:

- It tracks who matters to the inquiry and what role they play.
- Stakeholders can include ministries, agencies, NGOs, experts, industry groups, and citizen groups.
- It also tracks whether each stakeholder has submitted evidence and whether contact details are complete.
- The implementing-agencies section keeps the review grounded in the institutions responsible for delivery and oversight.

Belize example:

- The main stakeholder is `Belize Transparency Initiative`.
- It already has contact details and a linked submission.
- The implementing agency is `Ministry of the Public Service and Governance`.

## Consultations Tab

This is the engagement and evidence-intake workspace.

What to say:

- It keeps consultation events, planned engagement, written submissions, and linked evidence in one place.
- It distinguishes between activities that have already happened and activities still being planned.
- It also connects submissions back to named stakeholders and supporting documents.

Belize example:

- One completed consultation is recorded: `Roundtable on disclosure compliance`.
- One written submission is recorded from `Belize Transparency Initiative`.
- The consultation and submission are linked to the supporting document trail.

## Analysis Tab

This is where evidence becomes conclusions.

What to say:

- The analysis workspace captures findings and recommendations.
- Findings describe what the review concluded from the evidence.
- Recommendations describe what should happen next in response to those findings.
- This is the bridge from evidence gathering into reporting.

Belize example:

- Finding: `Proactive disclosure remains uneven across ministries`
- Recommendation: `Issue a standard disclosure directive`

## Reports Tab

This is the end-stage workspace for drafting outputs and tracking follow-up.

What to say:

- This tab moves the review from analysis into published outputs and post-publication follow-up.
- It can create draft and final reports, link them to publication files, and track government responses.
- It keeps report drafting and comply-or-explain follow-up attached to the same review record.
- This is the strongest tab for showing the full value of the prototype because it closes the loop from source material to institutional response.

Belize example:

- There is one final report on file.
- There is one government response attached to that report.
- The tab shows that analysis, reporting, and follow-up are meant to live in one continuous workspace rather than separate tools.

## Belize Review Summary

Use this review as the primary demo because it is the most complete production example.

Current Belize counts on production:

- 1 legislation record
- 5 documents
- 1 stakeholder
- 1 implementing agency
- 1 consultation
- 1 submission
- 1 finding
- 1 recommendation
- 1 report
- 1 government response

## One-Line Positioning for the Team

If you need a short summary, use this:

`The app is a structured PLS review workspace: it starts with defining the inquiry, then organizes legislation, documents, stakeholders, consultations, analysis, and reporting in one place so parliamentary teams can manage the full scrutiny lifecycle.`
