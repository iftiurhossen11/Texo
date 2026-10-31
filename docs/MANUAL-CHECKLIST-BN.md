# Texo — Manual Checklist (আপনাকে করতে হবে)

> Code/automation দিয়ে যা করা যায় সব করা হয়েছে।  
> Exhibition (৫ সেপ্টেম্বর ২০২৬) এর আগে নিচের items **আপনি personally** complete করুন।

---

## 🔴 Must do (exhibition critical)

### 1. Booth laptop setup
- [ ] পুরো project folder booth laptop এ copy করুন
- [ ] **PHP 8.2+ required** (Laravel 12) — XAMPP PHP 8.0 কাজ করবে না
  - Install: `winget install PHP.PHP.8.2`
  - Script auto-finds WinGet PHP 8.2 (XAMPP 8.0 ignore করে)
- [ ] Setup চালানোর আগে **RAG service বন্ধ** করুন (port 8001) — নাহলে venv install fail হতে পারে
- [ ] **Automated setup:** root থেকে run করুন:
  ```powershell
  powershell -ExecutionPolicy Bypass -File setup-booth-laptop.ps1
  ```
- [ ] `backend-laravel\.env` edit — `DB_PASSWORD`, `GROQ_API_KEY` set করুন
- [ ] Root থেকে: `start-demo.bat` → `pre-exhibition-check.bat` (health + warmup + QA একসাথে)
- [ ] Browser: http://127.0.0.1:5173

### 2. Venue wifi test (venue এ পৌঁছে যাওয়ার পর — ideally ৪ সেপ্টেম্বর)
- [ ] Booth laptop venue wifi তে connect
- [ ] Root থেকে run: **`venue-wifi-test.bat`**
- [ ] `warmup.bat` run — primary demo question answer আসে কিনা confirm
- [ ] **যদি Groq block হয়:** Claude API key set করুন (`LLM_PROVIDER=claude` in `.env`) — **শুধু ৪৮ ঘণ্টা আগে lock করুন, demo day তে switch করবেন না**

### 3. Screen recording backup
- [ ] Run: **`record-demo-backup.bat`** (instructions + reminder খুলবে)
- [ ] Clean 60–90s demo record করুন: indexed doc → broken stitch question → answer + context panel
- [ ] Save: **presenter phone** + `docs/screen-recording-backup.mp4`
- [ ] Recording একবার play করে corrupt নয় confirm করুন
- [ ] Instructions: `docs/screen-recording-INSTRUCTIONS.md`

### 4. Presenter rehearsal (3–5 times)
- [ ] Run: **`rehearsal-helper.bat`** — browser + script auto-open
- [ ] Script: `docs/demo-script.md`
- [ ] Primary Q: *"What should an inspector do for a broken stitch?"*
- [ ] প্রতিবার **New Visitor** button click between runs
- [ ] Target: 60–90 seconds total
- [ ] Backup Q (optional): production, compliance, HR, cyber — `demo-content/backup-qa-set.md`

### 5. Embedding latency on booth laptop
- [ ] `pre-exhibition-check.bat` বা `extended-qa.php` run — latency ms দেখুন
- [ ] Dev machine: **~66–162ms** (e5-small) — booth laptop এ confirm করুন
- [ ] যদি >3s feel হয়, BUILD_LOG দেখুন (e5-small already in use)

---

## 🟡 Should do (same day before exhibition)

### 6. Watchdog test
- [ ] `watchdog.bat` background window এ run
- [ ] Task Manager থেকে একটা service kill করুন (e.g. uvicorn)
- [ ] 30s এর মধ্যে auto-restart confirm

### 7. Chroma restore test
- [ ] `test-restore-chroma.bat` run (automated — dev machine verified ✅)
- [ ] Booth laptop এ একবার manually confirm করুন

### 8. Kiosk mode
- [ ] `start-kiosk.bat` — Chrome fullscreen
- [ ] Booth screen resolution এ layout clean দেখায় confirm

---

## 🟢 Optional

- [ ] Claude provider test (`LLM_PROVIDER=claude`) as emergency fallback only
- [ ] Second backup Q live demo practice
- [ ] Bangla-only question rehearsal: *"ভাঙা সেলাই পেলে ইন্সপেক্টর কী করবে?"*

---

## Demo day morning (BCFEC)

```bat
start-demo.bat
pre-exhibition-check.bat
start-kiosk.bat
```

**LLM locked:** Groq — do not change on demo day.

**Honesty:** ERP/IoT/RBAC — roadmap only, not live.

---

## Admin login (if needed)

- Email: `admin@texo.local`
- Password: `admin1234`

---

*Last updated: 2026-09-01*
