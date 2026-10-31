@echo off
REM Restore Chroma from backup (stop RAG first)
setlocal
cd /d "%~dp0\rag-service"

if not exist "chroma_data_backup" (
  echo [FAIL] chroma_data_backup not found — run backup-chroma.bat first.
  exit /b 1
)

echo Stopping any process on port 8001...
for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":8001" ^| findstr "LISTENING"') do (
  taskkill /PID %%a /F >nul 2>&1
)
timeout /t 2 /nobreak >nul

if exist "chroma_data" rmdir /s /q "chroma_data"
xcopy /E /I /Q "chroma_data_backup" "chroma_data" >nul

echo [OK] Restored chroma_data from backup.
echo Restart RAG: cd rag-service ^&^& venv\Scripts\uvicorn main:app --host 127.0.0.1 --port 8001
exit /b 0
