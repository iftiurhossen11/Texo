#!/usr/bin/env bash
# Texo — one-click demo start (Linux/macOS)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "Starting RAG service :8001..."
(cd rag-service && ./venv/bin/uvicorn main:app --host 127.0.0.1 --port 8001) &

echo "Starting Laravel :8000..."
(cd backend-laravel && php artisan serve --host=127.0.0.1 --port=8000) &

echo "Starting queue worker..."
(cd backend-laravel && php artisan queue:work --tries=3 --timeout=60) &

echo "Starting frontend :5173..."
(cd frontend-react && npm run dev) &

echo "All services launching."
echo "Open http://127.0.0.1:5173 for the kiosk UI."
wait
