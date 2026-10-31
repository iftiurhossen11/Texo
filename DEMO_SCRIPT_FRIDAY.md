# Texo Live Demo Script — TEXVERSE 2.0

**Event:** TEXVERSE 2.0  
**Date:** Friday, 30 October 2026  
**Demo URL:** http://127.0.0.1:5173  
**Target duration:** 8–10 minutes

## Pre-Demo Checklist

1. Start the project with `start-demo.bat`.
2. Run `health-check.bat` and confirm RAG, Laravel, Frontend, and Queue are all `[OK]`.
3. Keep the four demo files ready in `demo-content/`.
4. Confirm the embedding model is warm by asking one short test question or running the warmup routine.
5. Keep the browser at `http://127.0.0.1:5173` and select English (`EN`) for the main presentation.

## Introduction — What Is Texo?

**Say:**

> Texo is an AI Knowledge Assistant for Bangladesh's textile and apparel industry. It uses retrieval-augmented generation, or RAG, so answers are grounded in the factory documents that a team uploads—not invented from a generic chatbot memory.
>
> The system is security-first: documents stay within the controlled application flow, requests are validated, and the design supports role-based access so production, QA, HR, compliance, and IT teams can receive the right knowledge for their work.

**Show:**

- Texo branding and bilingual interface
- Upload panel and document status flow
- The source-passage panel beneath the chat

## Demo 1 — QA: Broken Stitch Procedure

**Upload:** `demo-content/garment-quality-inspection-sop.md`  
**Document type:** SOP  
**Department:** QA

**Ask:**

> What should an inspector do for a broken stitch?

**Expected talking points:**

- Tag the garment as defective.
- Move it to the rejection bin or controlled hold area.
- Record the defect in the QA system.
- Notify the quality supervisor.

**Say:**

> The important point is traceability. Texo does not only produce an answer; it shows the source passage and the document that supports the answer.

## Demo 2 — Compliance: Buyer Requirements

**Upload:** `demo-content/buyer-compliance-hm-zara.txt`  
**Document type:** Buyer requirement  
**Department:** Compliance & Sustainability

**Ask:**

> What are the AQL requirements for major defects?

**Expected talking points:**

- The QA reference sets major defects below 2.5%.
- Minor defects are below 4.0%.
- Critical defects have zero tolerance.
- Explain that the compliance document also connects the audit discussion to worker rights, safety, wages, and no child labor.

**Say:**

> This is a useful gap-detection moment: Texo can surface the numerical quality threshold while the manager reviews the wider buyer-compliance checklist.

## Demo 3 — HR: Day-One PPE

**Upload:** `demo-content/hr-osh-safety-policy.md`  
**Document type:** HR policy  
**Department:** HR & Training

**Ask:**

> What PPE is needed on Day 1?

**Expected talking points:**

- Safety footwear.
- Hairnet and mask where required.
- Task-specific gloves, safety glasses, hearing protection, apron, or sleeve protection.
- PPE issue must be recorded with worker ID, size, date, and acknowledgement.

**Say:**

> The answer is operational: a new floor employee can ask the question and receive the policy steps without searching through a long manual.

## Demo 4 — Cyber: Suspicious Email

**Upload:** `demo-content/cyber-phishing-awareness.txt`  
**Document type:** Cybersecurity policy  
**Department:** IT & Cybersecurity

**Ask:**

> How should an employee report a suspicious email?

**Expected talking points:**

- Do not click links or open unexpected attachments.
- Forward the message to `IT-Security@factory.com`.
- Preserve the original email.
- Contact IT Security immediately if credentials were entered or a link was clicked.

**Say:**

> The same knowledge workflow works for cyber awareness. A factory employee can get a direct, safe action instead of guessing what to do.

## Closing — Gap Detection and RBAC

**Say:**

> Texo's value is not just chat. It connects people to approved operational knowledge, exposes the supporting source passage, and helps identify gaps when a question has no grounded answer.
>
> With role-based access, a QA inspector, HR manager, compliance lead, and IT security team can work from the documents relevant to their responsibilities. That creates a practical bridge between factory operations, buyer requirements, worker safety, and cybersecurity.

**Final action:**

1. Point to the retrieved source passage.
2. Show the document status as `Indexed`.
3. Ask the audience for a department-specific question.
4. Close with: **“Texo turns scattered factory knowledge into an auditable, searchable assistant.”**

## Recovery Plan

- If upload remains `Processing`, wait for the queue worker and refresh the document list.
- If an answer says no matching passage was found, confirm the document is `Indexed` and ask a question using wording from the document.
- If the LLM provider is unavailable, explain that retrieval and citations are still working, then show the retrieved source passage.
- Do not display API keys, `.env` files, internal logs, or personal worker data during the exhibition.
