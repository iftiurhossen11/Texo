@echo off
REM Backup Chroma vector store before demo day
setlocal
cd /d "%~dp0\rag-service"

if not exist "chroma_data" (
  echo [FAIL] chroma_data not found — index demo content first.
  exit /b 1
)

if exist "chroma_data_backup" rmdir /s /q "chroma_data_backup"
xcopy /E /I /Q "chroma_data" "chroma_data_backup" >nul

echo [OK] Chroma backup saved to rag-service\chroma_data_backup\
dir /s chroma_data_backup 2>nul | find "File(s)"
exit /b 0
