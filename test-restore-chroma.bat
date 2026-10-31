@echo off
REM Verify Chroma backup + restore cycle
setlocal
cd /d "%~dp0"

echo === Step 1: Fresh backup ===
call backup-chroma.bat
if errorlevel 1 exit /b 1

echo.
echo === Step 2: Warmup before restore ===
call warmup.bat
if errorlevel 1 exit /b 1

echo.
echo === Step 3: Restore from backup ===
call restore-chroma.bat
if errorlevel 1 exit /b 1

echo.
echo === Step 4: Restart RAG ===
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8001" ^| findstr "LISTENING"') do taskkill /PID %%a /F >nul 2>&1
ping -n 3 127.0.0.1 >nul
start "Texo RAG" /MIN cmd /c "cd /d "%~dp0rag-service" && venv\Scripts\uvicorn main:app --host 127.0.0.1 --port 8001"
ping -n 12 127.0.0.1 >nul

echo.
echo === Step 5: Post-restore warmup ===
call warmup.bat
if errorlevel 1 (
  echo [FAIL] Restore verification failed
  exit /b 1
)

echo.
echo [OK] Chroma backup + restore verified
exit /b 0
