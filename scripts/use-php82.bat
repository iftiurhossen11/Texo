@echo off
REM Prepend PHP 8.2+ to PATH (call before any php/composer command)
set "PHP_DIR="
for /f "delims=" %%i in ('"%~dp0resolve-php82.cmd"') do set "PHP_DIR=%%i"
if not defined PHP_DIR (
  echo [FAIL] PHP 8.2+ not found.
  echo        Install: winget install PHP.PHP.8.2
  echo        Or ensure PHP 8.2 is in PATH before XAMPP PHP 8.0.
  exit /b 1
)
echo [OK]   PHP 8.2+ - %PHP_DIR%
set "PATH=%PHP_DIR%;%PATH%"
exit /b 0
