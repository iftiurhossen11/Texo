@echo off
REM Opens demo UI + prints rehearsal script for presenter
setlocal
cd /d "%~dp0"

echo.
echo ============================================
echo   TEXO — Demo Rehearsal Helper
echo ============================================
echo.
echo TARGET: 60-90 seconds total
echo.
echo STEP 1  Introduce Texo (5s)
echo STEP 2  Show Indexed Documents — SOP already indexed (10s)
echo STEP 3  Click NEW VISITOR if chat visible (5s)
echo STEP 4  Ask: "What should an inspector do for a broken stitch?" (15s)
echo STEP 5  Point to Retrieved Source Passages + read answer (25s)
echo STEP 6  Scroll Other Department Examples (15s)
echo STEP 7  Click NEW VISITOR before next visitor (5s)
echo.
echo Full script: docs\demo-script.md
echo.

start "" "http://127.0.0.1:5173"
timeout /t 2 /nobreak >nul
start notepad "%~dp0docs\demo-script.md"

echo Browser opened. Rehearse 3-5 times. Press any key when done...
pause >nul
