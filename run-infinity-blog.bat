@echo off
title Infinity Blog - 1-Click Server
setlocal enabledelayedexpansion

echo =======================================================
echo          INFINITY BLOG - LOCAL SERVER LAUNCHER
echo =======================================================
echo.

set "PHP_CMD="

:: 1. Check if php is in system PATH
where php >nul 2>nul
if %ERRORLEVEL% equ 0 (
    set "PHP_CMD=php"
    goto :FOUND_PHP
)

:: 2. Check common XAMPP and custom locations
if exist "C:\xampp\php\php.exe" (
    set "PHP_CMD=C:\xampp\php\php.exe"
    goto :FOUND_PHP
)
if exist "D:\xampp\php\php.exe" (
    set "PHP_CMD=D:\xampp\php\php.exe"
    goto :FOUND_PHP
)
if exist "C:\tools\php\php.exe" (
    set "PHP_CMD=C:\tools\php\php.exe"
    goto :FOUND_PHP
)

:: 3. If PHP not found, show instructions
echo [ERROR] PHP executable was not found on your computer.
echo.
echo To run Infinity Blog with full database support:
echo  1. Install XAMPP from: https://www.apachefriends.org/
echo  2. Move this 'infinity-blog' folder into: C:\xampp\htdocs\infinity-blog
echo  3. Open XAMPP Control Panel and click [Start] for Apache and MySQL.
echo  4. Open your browser to: http://localhost/infinity-blog/
echo.
echo (Press any key to open the offline preview in your browser instead...)
pause >nul
start "" "%~dp0index.html"
exit /b 1

:FOUND_PHP
echo [OK] Using PHP: !PHP_CMD!
echo.
echo Starting Infinity Blog server at: http://localhost:8000/
echo Press Ctrl+C at any time in this window to stop the server.
echo.

:: Automatically open browser after 1 second in background
start "" cmd /c "timeout /t 1 /nobreak >nul && start http://localhost:8000/index.html"

:: Run PHP built-in web server
cd /d "%~dp0"
!PHP_CMD! -S localhost:8000 router.php
