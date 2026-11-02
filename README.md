# Texo

Texo helps Bangladesh's textile and apparel teams organize department-specific documents, index them for search, and ask questions with AI-generated answers grounded in relevant source passages.

**Live website:** [Open Texo](https://frontend-react-rho-two.vercel.app/)

Company Owner and Factory Owner accounts can upload documents. Set `TEXO_OWNER_EMAILS` to a comma-separated allowlist and configure a long random `TEXO_OWNER_SETUP_CODE` in the Laravel environment. During registration, users can choose Standard User, Company Owner, or Factory Owner; owner types require an allowlisted email and the setup code. Public registration cannot grant privileged owner access by selecting a dropdown value alone. All accounts currently use the same shared workspace; separate company/factory data isolation is not enabled.

## Stack

| Layer | Tech |
|---|---|
| Core backend | Laravel 12 + MySQL 8 + Sanctum |
| RAG microservice | Python 3.11+ / FastAPI / Chroma |
| Frontend | React + TypeScript + Vite + Tailwind |
| LLM | Claude (primary) / Groq (fallback) |

## Quick Start

```bash
# 1. Laravel (port 8000) — use PHP 8.2+
cd backend-laravel
cp .env.example .env   # set DB_* and API keys
composer install
php artisan migrate
php artisan serve

# 2. Queue worker (single instance only)
php artisan queue:work

# 3. RAG service (port 8001)
cd rag-service
python -m venv venv
venv\Scripts\activate
pip install -r requirements.txt
uvicorn main:app --host 127.0.0.1 --port 8001

# 4. Frontend (port 5173)
cd frontend-react
npm install
npm run dev
```

Or on Windows:

From PowerShell or Command Prompt, run these commands from the project root:

```powershell
.\start-demo.bat
.\stop-demo.bat
```

`start-demo.bat` starts the services in the background without opening a separate console for each service. It runs pending Laravel migrations and waits up to three minutes for the frontend, Laravel, and RAG health endpoints; the first RAG startup can take longer while the embedding model loads. Service output is written to `backend-laravel/storage/logs/`.

Open `http://127.0.0.1:5173` after the launcher reports that all services are healthy. If a port is already in use, run `.\stop-demo.bat` and retry.

First-time booth laptop: `powershell -ExecutionPolicy Bypass -File setup-booth-laptop.ps1`

See `docs/MANUAL-CHECKLIST-BN.md` for exhibition-day steps.

## Ports

| Service | Port |
|---|---|
| Laravel | 8000 |
| RAG (FastAPI) | 8001 |
| MySQL | 3306 |
| Vite dev | 5173 |

## Demo Content

Sample SOP and Q&A sets live in `demo-content/`.

## Documentation

- Master rules: `.cursor/rules/00-master-rules.mdc`
- Phase prompts: `docs/01-phase0-...` through `docs/07-phase6-...`
