@echo off
REM ============================================================
REM  EstateOS - start the local development environment
REM  Starts MariaDB (XAMPP) if needed, then the PHP dev server.
REM ============================================================

setlocal
set PHP=C:\xampp\php\php.exe
set MYSQLD=C:\xampp\mysql\bin\mysqld.exe
set MYSQL=C:\xampp\mysql\bin\mysql.exe
set PORT=8787
cd /d "%~dp0"

if not exist "%PHP%" (
  echo [!] PHP not found at %PHP%
  echo     Install XAMPP, or edit the PHP path at the top of this file.
  pause
  exit /b 1
)

echo.
echo  EstateOS - local environment
echo  ----------------------------

REM --- database ------------------------------------------------
"%MYSQL%" -u root -h 127.0.0.1 -e "SELECT 1" >nul 2>&1
if errorlevel 1 (
  echo  [*] Starting MariaDB...
  start "EstateOS MariaDB" /min "%MYSQLD%" --defaults-file=C:\xampp\mysql\bin\my.ini --standalone
  REM give the server a moment to accept connections
  for /l %%i in (1,1,20) do (
    timeout /t 1 /nobreak >nul
    "%MYSQL%" -u root -h 127.0.0.1 -e "SELECT 1" >nul 2>&1
    if not errorlevel 1 goto :db_up
  )
  echo  [!] MariaDB did not start. Open the XAMPP Control Panel and start MySQL.
  pause
  exit /b 1
) else (
  echo  [*] MariaDB already running
)
:db_up

REM --- schema --------------------------------------------------
"%MYSQL%" -u root -h 127.0.0.1 -e "USE estateos" >nul 2>&1
if errorlevel 1 (
  echo  [*] Creating schema and seeding demo data...
  "%PHP%" database\migrate.php
  "%PHP%" database\seed.php --demo
  "%PHP%" database\seed-phase2.php
  "%PHP%" database\seed-phase4.php
  "%PHP%" database\seed-phase5.php
  "%PHP%" database\seed-phase6.php
) else (
  echo  [*] Applying any pending migrations...
  "%PHP%" database\migrate.php
)

REM --- web server ----------------------------------------------
echo.
echo  Ready:  http://localhost:%PORT%
echo  Sign in: owner@estateos.test  /  Password123!
echo.
echo  Press Ctrl+C to stop the web server.
echo.
start "" http://localhost:%PORT%/login
"%PHP%" -S localhost:%PORT% -t public server.php

endlocal
