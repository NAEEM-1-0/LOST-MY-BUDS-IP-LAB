@echo off
title BudsRecover - IP Lab PHP Server (Port 8080)
color 0b

echo ===============================================================================
echo            BudsRecover - Internet Programming (IP) Lab PHP Server
echo ===============================================================================
echo.
echo [*] Checking PHP executable in XAMPP...

if exist "C:\xampp\php\php.exe" (
    set PHP_EXE=C:\xampp\php\php.exe
) else (
    set PHP_EXE=php
)

echo [*] Using PHP: %PHP_EXE%
echo [*] Document Root: %~dp0php_portal
echo.
echo ===============================================================================
echo  PHP Server running at: http://127.0.0.1:8080/
echo.
echo  Available IP Lab Pages:
echo   - Main Dashboard:        http://127.0.0.1:8080/index.php
echo   - Serial Verification:   http://127.0.0.1:8080/verify_serial.php
echo   - Warranty Claim Form:   http://127.0.0.1:8080/warranty_claim.php
echo   - Printable Invoice:     http://127.0.0.1:8080/invoice.php
echo   - Admin Panel (Session): http://127.0.0.1:8080/admin_portal.php
echo   - JSON REST API:         http://127.0.0.1:8080/api.php?action=stats
echo   - Diagnostics Check:     http://127.0.0.1:8080/test_connection.php
echo ===============================================================================
echo.
echo Press Ctrl+C anytime to stop the PHP server.
echo.

"%PHP_EXE%" -S 127.0.0.1:8080 -t "%~dp0php_portal"

pause
