@echo off
REM Test outbound HTTPS to LLM providers — run at venue on booth wifi
setlocal
echo === Venue Wi-Fi / LLM Connectivity Test ===
echo.

set FAIL=0

curl.exe -sfI --max-time 10 https://api.groq.com >nul 2>&1
if errorlevel 1 (
  echo [FAIL] Cannot reach api.groq.com — Groq will not work!
  set FAIL=1
) else (
  echo [OK]   api.groq.com reachable
)

curl.exe -sfI --max-time 10 https://api.anthropic.com >nul 2>&1
if errorlevel 1 (
  echo [WARN] api.anthropic.com not reachable — Claude fallback unavailable
) else (
  echo [OK]   api.anthropic.com reachable
)

echo.
if %FAIL%==1 (
  echo RESULT: GROQ BLOCKED — set LLM_PROVIDER=claude in .env at least 48h before demo
  echo         OR use Ollama local fallback if configured.
  exit /b 1
)
echo RESULT: LLM connectivity OK for Groq demo
exit /b 0
