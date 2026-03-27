# PLS Bot — AI Behavior & Governance Framework for WFD

## 1. Purpose

This document explains how AI will behave within the PLS Bot prototype in a way that is accessible to WFD stakeholders and project partners.

It is intended to clarify:
- what the AI is designed to do
- what the AI is not designed to do
- how human judgment remains central
- how AI behavior can evolve over time through controlled administration

This is a product and governance document, not a technical implementation document.

---

## 2. Core Position

The PLS Bot does not treat AI as a generic chatbot.

Instead, the application uses AI as a **process-aware assistant** embedded in a structured PLS workflow.

That means the AI is designed to:
- support practitioners as they move through an inquiry
- adapt its help based on the section of the application in use
- remain grounded in available evidence and configured guidance
- clearly acknowledge when available information is insufficient

The AI is not designed to replace expert judgment, parliamentary discretion, or field-based evidence gathering.

---

## 3. What the AI Does

The AI is intended to help users:
- understand where they are in a PLS inquiry
- identify missing materials or missing steps
- analyze uploaded documents
- structure key questions for inquiry planning
- map stakeholders and consultation needs
- organize evidence into draft findings
- support report drafting in a clearly provisional way

The AI is therefore a support layer for inquiry design, evidence organization, and workflow guidance.

---

## 4. What the AI Does Not Do

The AI is not intended to:
- fabricate information
- answer questions without evidence
- substitute for interviews, consultation, or qualitative field work
- make final recommendations on behalf of practitioners
- produce final findings without human review and validation

Where evidence is insufficient, the AI should explicitly say so.

This is a design requirement, not an edge case.

---

## 5. Tab-Aware AI Behavior

A core design feature of the PLS Bot is that the AI panel remains present throughout the application but changes its behavior depending on the active tab.

This means the user experiences one continuous assistant, but that assistant works differently in different parts of the system.

### Workflow Tab
In the workflow tab, the AI behaves as a process guide. It helps users understand where they are in the inquiry and what steps typically come next.

### Documents Tab
In the documents tab, the AI behaves as a document intelligence assistant. It helps identify missing materials, summarize uploaded files, compare documents, and flag potential gaps.

### Legislation Tab
In the legislation tab, the AI behaves as a legislation-focused assistant. It can help users understand the structure of a law, identify implementation obligations, and flag where secondary legislation may be required.

### Collaborators Tab
In the collaborators tab, the AI behaves as a coordination assistant. It can help identify missing expertise or suggest how collaboration may be organized.

### Stakeholders Tab
In the stakeholders tab, the AI behaves as a stakeholder mapping assistant. It can help surface likely affected groups and identify gaps in representation.

### Consultation Tab
In the consultation tab, the AI behaves as an engagement design assistant. It can help generate consultation questions and suggest suitable consultation formats.

### Analysis / Report Tab
In the analysis and reporting area, the AI behaves as a drafting and structuring assistant. It can help organize evidence and suggest potential findings or recommendation options, but these remain provisional unless validated by the practitioner.

---

## 6. Allowed vs. Not Allowed by Tab

To support trust and consistency, the AI will operate within explicit capability boundaries in each tab.

For example:
- in the workflow tab, it may explain stages and suggest next steps, but it should not generate findings
- in the documents tab, it may summarize and compare uploaded materials, but it should not assume that missing documents exist
- in the analysis area, it may generate potential findings, but it should not present them as final conclusions

This capability-boundary approach is important because it prevents the assistant from overreaching beyond what the user has asked it to do and beyond what the available evidence supports.

---

## 7. Evidence and Uncertainty

The prototype is designed around evidence-based interaction.

This means:
- the AI should rely on uploaded documents and approved source materials
- the AI should distinguish between what is present and what is missing
- the AI should not infer certainty where evidence is incomplete

If a user asks the AI for something the system cannot responsibly provide, the expected behavior is explicit limitation language, such as:

> "I do not have sufficient information to answer this."

This is especially important in impact-related questions, where real-world outcomes often depend on qualitative and quantitative data that may not yet have been uploaded or collected.

---

## 8. Human-in-the-Loop Model

The PLS Bot is built on the assumption that human expertise remains central throughout the inquiry.

The AI helps users move faster and with more structure, but it does not replace:
- expert legal interpretation
- political judgment
- stakeholder engagement
- qualitative inquiry design
- final decision making

In practice, this means AI-generated outputs should be framed as:
- draft
- potential
- provisional

The final result remains the responsibility of the practitioner or inquiry team.

---

## 9. Administrative Control and Continuous Improvement

A key feature of the design is that certain aspects of AI behavior will be configurable by admin users.

This means that over time, the system can reflect:
- institutional learning
- updated methodology
- country-specific practice
- improvements suggested by users and WFD partners

Importantly, this is not intended to create an uncontrolled prompt-editing environment.

Instead, the system is designed around structured, versioned configuration by tab. Admin users will be able to update defined fields such as:
- role description
- objectives
- suggested prompts
- rules
- guardrails

This allows the AI to improve over time without losing consistency or governance.

---

## 10. Governance Value of the Configuration Layer

This administrative configuration model offers several benefits for WFD and partners:

### It supports continuous improvement
As inquiry teams learn what works, those lessons can be encoded in the assistant.

### It supports contextual adaptation
Different jurisdictions or institutions may want to emphasize different practices.

### It supports transparency
Changes to AI behavior can be reviewed, versioned, and explained.

### It supports trust
Users can have greater confidence in the system when its behavior is intentional, bounded, and reviewable.

---

## 11. Versioning and Accountability

AI behavior should not change invisibly.

For that reason, the configuration layer is expected to support versioning, including:
- version history
- change notes
- rollback capability
- the ability to identify which behavior version was active at a given time

This is important for governance, debugging, and product trust.

---

## 12. Why This Matters for Adoption

The success of the PLS Bot will depend not only on whether the AI is useful, but on whether users trust it enough to integrate it into real inquiry work.

This design addresses that by ensuring the AI is:
- structured rather than generic
- grounded rather than speculative
- supportive rather than substitutive
- configurable rather than static
- transparent rather than opaque

In short, the AI is being designed to work in a way that fits the real practice of post-legislative scrutiny.

---

## 13. Summary

The PLS Bot uses AI as a structured support layer within a PLS inquiry workflow.

Its defining characteristics are:
- tab-aware behavior
- explicit capability boundaries
- evidence-based responses
- human-in-the-loop control
- admin-configurable evolution over time

This approach is intended to make the assistant more useful, more trustworthy, and more adaptable to the needs of practitioners and institutions working in PLS.

