#!/usr/bin/env bash
# Texo — pre-doors-open warm-up (Linux/macOS)
set -euo pipefail
ROOT="$(cd "$(dirname "$0")" && pwd)"
cd "$ROOT"

echo "=== Texo Warm-up ==="
echo "[$(date +%H:%M:%S)] Step 1/4: Health check..."
./health-check.sh

echo
echo "[$(date +%H:%M:%S)] Step 2/4: Warm embedding model (retrieve)..."
curl -sf -X POST "http://127.0.0.1:8001/retrieve" \
  -H "Content-Type: application/json" \
  -d '{"query":"warmup broken stitch","top_k":1}' >/dev/null
echo "[OK]   RAG retrieve"

echo
echo "[$(date +%H:%M:%S)] Step 3/4: Warm LLM (primary demo question)..."
php backend-laravel/scripts/warmup-chat.php

echo
echo "[$(date +%H:%M:%S)] Step 4/4: Second chat query (no-answer calibration)..."
php backend-laravel/scripts/warmup-chat.php no-answer

echo
echo "=== Warm-up complete — booth ready ==="
