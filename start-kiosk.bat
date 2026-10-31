@echo off
REM Open kiosk UI in Chrome kiosk mode (presenter use)
start "" "C:\Program Files\Google\Chrome\Application\chrome.exe" --kiosk --disable-pinch --overscroll-history-navigation=0 http://127.0.0.1:5173
if errorlevel 1 (
  echo Chrome not found at default path. Open http://127.0.0.1:5173 manually in kiosk mode.
)
