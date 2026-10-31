# Texo — 60–90 Second Exhibition Demo Script

**Event:** 25th Bangladesh Textile Series — Digital Transformation Zone  
**Date:** 5 September 2026, BCFEC Purbachal  
**Audience:** Factory supervisors, QA, compliance, buyers, IT staff

---

## Before the booth opens

1. Run `start-demo.bat` (starts RAG, Laravel, queue worker, frontend)
2. Run `health-check.bat` — all three services must show OK
3. Run `warmup.bat` — warms embedding model + LLM connection
4. Optional: run `watchdog.bat` in a background window for auto-restart
5. Open kiosk: `start-kiosk.bat` or Chrome → http://127.0.0.1:5173

**Pre-indexed content:** Garment Quality Inspection SOP is already indexed. You do **not** need to upload live unless demonstrating the upload flow.

---

## Primary demo (60–90 seconds)

| Step | Action | What visitor sees |
|------|--------|-------------------|
| 1 (5s) | Introduce Texo | "AI knowledge assistant for factory SOPs — grounded answers, not guesses." |
| 2 (10s) | Point to **Indexed Documents** | SOP already indexed — skip upload for speed |
| 3 (5s) | Click **New Visitor** if previous chat visible | Clean slate for this visitor |
| 4 (15s) | Type: **"What should an inspector do for a broken stitch?"** | Retrieved passages appear in right panel |
| 5 (20s) | Read key steps from answer | Stop line → tag BS-01 → rework bin → QC log → notify supervisor |
| 6 (10s) | Point to **Retrieved Source Passages** | "Answer comes from your document — not invented." |
| 7 (15s) | Scroll **Other Department Examples** | "Same system works for HR, compliance, cyber, supply chain." |
| 8 (10s) | Optional backup Q | Pick one from `demo-content/backup-qa-set.md` |

---

## Backup questions (if time allows)

- **Production:** "What should happen when a defective piece is found at inline inspection?"
- **Compliance:** "What AQL for major defects during a buyer audit?"
- **HR:** "What must a new floor employee complete on first day?"
- **Cyber:** "First step if employee receives a suspicious phishing email?"

---

## No-answer demo (trust moment)

Ask: **"What is the capital city of Mars and its official textile export policy?"**

Expected: honest bilingual "not found" — no hallucination, empty context panel.

---

## Between visitors

Always click **New Visitor** (top-right) before the next person.

---

## If live system fails

1. Check `health-check.bat`
2. Restart with `start-demo.bat`
3. Fall back to screen recording on presenter's phone (`docs/screen-recording-backup.mp4`)
4. Narrate the same script over the recording

---

## Honesty talking points (if asked)

| Claim | Tier |
|-------|------|
| Document upload, search, grounded Q&A | ✅ Live today |
| ERP / IoT / Smart Factory integration | 🔮 Roadmap only |
| Full RBAC, encryption, audit logging | 🔮 Roadmap only |

---

## LLM provider (locked)

- **Provider:** Groq (`openai/gpt-oss-20b`)
- **Locked:** 1 September 2026 — do not switch before exhibition
