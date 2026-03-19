@echo off
setlocal
title Intern Management System - Professional Launcher

:: 1. CHECK & START MYSQL
tasklist /fi "imagename eq mysqld.exe" | find ":" > nul
if errorlevel 1 (
    echo [DATABASE] Starting MySQL Service...
    :: If running from your XAMPP folder:
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start /b "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini"
    ) 
    :: If running from a portable Delivery folder:
    if exist ".\database\bin\mysqld.exe" (
        start /b "" ".\database\bin\mysqld.exe" --defaults-file=".\database\bin\my.ini"
    )
    timeout /t 3 > nul
) else (
    echo [DATABASE] MySQL is already running.
)

:: 2. CHECK & START APACHE (Optional if using PHP Desktop)
tasklist /fi "imagename eq httpd.exe" | find ":" > nul
if errorlevel 1 (
    echo [SERVER] Starting Apache Web Server...
    if exist "C:\xampp\apache\bin\httpd.exe" (
        start /b "" "C:\xampp\apache\bin\httpd.exe"
    )
    timeout /t 2 > nul
) else (
    echo [SERVER] Apache is already running.
)

:: 3. LAUNCH THE APPLICATION
echo [APP] Opening Intern Management System...

:: CASE A: Using standard browser (for your testing)
start http://localhost/imsjr/index.php

:: CASE B: For Delivery (Uncomment the line below when you have PHP Desktop)
:: if exist "php_desktop\php_desktop_chrome.exe" start "" "php_desktop\php_desktop_chrome.exe"

echo.
echo ==============================================
echo   SYSTEM IS NOW ACTIVE
echo   You can minimize this window.
echo   Closing this window may stop the database.
echo ==============================================

pause > nul
