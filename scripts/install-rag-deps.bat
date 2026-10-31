@echo off
REM Install / repair RAG Python dependencies
setlocal
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0install-rag-deps.ps1" %*
set EXIT=%ERRORLEVEL%
if %EXIT% NEQ 0 exit /b %EXIT%
exit /b 0
