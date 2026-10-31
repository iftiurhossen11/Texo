@echo off
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\health-check.ps1"
exit /b %errorlevel%
