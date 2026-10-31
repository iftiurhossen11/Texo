@echo off
REM Lightweight service watchdog — restarts crashed demo processes
setlocal enabledelayedexpansion
call "%~dp0scripts\use-php82.bat"
if errorlevel 1 exit /b 1
cd /d "%~dp0"

echo Texo Watchdog — checking every 30s (Ctrl+C to stop)
:loop
  call :check_port 8001 "rag-service" "venv\Scripts\uvicorn main:app --host 127.0.0.1 --port 8001"
  call :check_port 8000 "backend-laravel" "php artisan serve --host=127.0.0.1 --port=8000"
  call :check_port 5173 "frontend-react" "npm run dev"
  timeout /t 30 /nobreak >nul
goto loop

:check_port
  netstat -ano | findstr ":%1 " | findstr "LISTENING" >nul 2>&1
  if errorlevel 1 (
    echo [%TIME%] RESTARTING %2 on port %1...
    start "Texo %2" cmd /k "cd %2 && %3"
  )
  exit /b 0
