@echo off
REM Start all Texo services in the background without opening a console per service.
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-demo.ps1"
if errorlevel 1 exit /b 1
echo Texo is starting. Open http://127.0.0.1:5173
