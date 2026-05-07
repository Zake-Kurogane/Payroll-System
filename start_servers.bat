@echo off
title Payroll System - Start Servers

echo === Stopping any existing processes ===
taskkill /F /IM php.exe /T >nul 2>&1
taskkill /F /IM httpd.exe /T >nul 2>&1
taskkill /F /IM mysqld.exe /T >nul 2>&1
timeout /t 2 /nobreak >nul

echo === Starting MariaDB ===
start "" /B "c:\xampp\mysql\bin\mysqld.exe" --defaults-file="c:\xampp\mysql\bin\my.ini" --standalone
timeout /t 5 /nobreak >nul

echo === Starting Apache ===
start "" /B "c:\xampp\apache\bin\httpd.exe"
timeout /t 5 /nobreak >nul

echo.
echo === Status ===
netstat -an | findstr ":3306.*LISTEN" >nul && echo MariaDB: RUNNING on :3306 || echo MariaDB: FAILED
netstat -an | findstr ":8000.*LISTEN" >nul && echo Apache:  RUNNING on :8000 || echo Apache:  FAILED

echo.
echo Payroll system ready at http://192.168.10.88:8000
echo.
pause
