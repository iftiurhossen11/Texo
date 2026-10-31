#!/usr/bin/env bash
# Texo — quick health check (Linux/macOS)
set -euo pipefail

FAIL=0
check() {
  local name="$1" url="$2"
  if curl -sf "$url" >/dev/null 2>&1; then
    echo "[OK]   $name"
  else
    echo "[FAIL] $name"
    FAIL=1
  fi
}

echo "=== Texo Health Check ==="
echo
check "RAG :8001"      "http://127.0.0.1:8001/health"
check "Laravel :8000"  "http://127.0.0.1:8000/api/health"
check "Frontend :5173" "http://127.0.0.1:5173"
echo
if [ "$FAIL" -eq 1 ]; then
  echo "RESULT: UNHEALTHY — run ./start-demo.sh"
  exit 1
fi
echo "RESULT: ALL SERVICES OK"
