@echo off
REM One-click pre-exhibition check — run on booth laptop morning-of or day before
setlocal
cd /d "%~dp0"

echo ============================================
echo   TEXO — Pre-Exhibition Full Check
echo ============================================
echo.

call health-check.bat
if errorlevel 1 (
  echo [STOP] Fix services first: start-demo.bat
  exit /b 1
)

echo.
call warmup.bat
if errorlevel 1 exit /b 1

echo.
call venue-wifi-test.bat

echo.
powershell -ExecutionPolicy Bypass -File "%~dp0scripts\phase6-qa.ps1"
if errorlevel 1 (
  echo [WARN] Some QA checks failed — review output above
)

echo.
echo ============================================
echo   Pre-exhibition automated checks done.
echo   Manual: rehearsal + screen recording still needed.
echo   See docs\MANUAL-CHECKLIST-BN.md
echo ============================================
exit /b 0
