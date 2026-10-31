@echo off
REM Texo — pre-doors-open warm-up (Windows)
REM Run after start-demo.bat, before booth opens.
setlocal
call "%~dp0scripts\use-php82.bat"
if errorlevel 1 exit /b 1
cd /d "%~dp0"

echo === Texo Warm-up ===
echo [%TIME%] Step 1/4: Health check...
call health-check.bat
if errorlevel 1 exit /b 1

echo.
echo [%TIME%] Step 2/4: Warm embedding model (retrieve)...
curl.exe -sf -X POST "http://127.0.0.1:8001/retrieve" -H "Content-Type: application/json" -d "{\"query\":\"warmup broken stitch\",\"top_k\":1}" >nul
if errorlevel 1 (echo [FAIL] RAG retrieve & exit /b 1)
echo [OK]   RAG retrieve

echo.
echo [%TIME%] Step 3/4: Warm LLM (primary demo question)...
php backend-laravel\scripts\warmup-chat.php
if errorlevel 1 (echo [FAIL] Chat warm-up & exit /b 1)

echo.
echo [%TIME%] Step 4/4: Second chat query (no-answer calibration)...
php backend-laravel\scripts\warmup-chat.php no-answer
if errorlevel 1 (echo [FAIL] No-answer warm-up & exit /b 1)

echo.
echo === Warm-up complete — booth ready ===
exit /b 0
