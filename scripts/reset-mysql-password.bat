@echo off
REM Run this script as Administrator (right-click -> Run as administrator)
REM Resets MySQL 8.0 root password for Texo local dev

set MYSQL_BIN=C:\Program Files\MySQL\MySQL Server 8.0\bin
set INIT_FILE=%TEMP%\mysql-init-texo.sql
set NEW_PASSWORD=Raiyan15420

echo ALTER USER 'root'@'localhost' IDENTIFIED BY '%NEW_PASSWORD%';> "%INIT_FILE%"
echo FLUSH PRIVILEGES;>> "%INIT_FILE%"

echo Stopping MySQL80 service...
net stop MySQL80

echo Starting mysqld with init-file to reset password...
"%MYSQL_BIN%\mysqld.exe" --defaults-file="C:\ProgramData\MySQL\MySQL Server 8.0\my.ini" --init-file="%INIT_FILE%"

echo Starting MySQL80 service...
net start MySQL80

echo Testing connection...
"%MYSQL_BIN%\mysql.exe" -u root -p%NEW_PASSWORD% -e "CREATE DATABASE IF NOT EXISTS texo_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; SELECT 'MySQL reset OK' AS result;"

del "%INIT_FILE%"
pause
