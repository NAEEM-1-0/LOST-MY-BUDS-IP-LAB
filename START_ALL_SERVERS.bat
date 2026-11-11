@echo off
title BudsRecover - Dual Server Runner (Flask + PHP)
color 0a

echo ===============================================================================
echo            BudsRecover - Dual Engine Launcher (Flask + PHP)
echo            Software Engineering & Internet Programming (IP) Lab
echo ===============================================================================
echo.
echo [*] Starting PHP IP Lab Server on http://127.0.0.1:8080 ...
start "BudsRecover - PHP Server (Port 8080)" cmd /k ""%~dp0START_PHP_SERVER.bat""

echo [*] Starting Python Flask Server on http://127.0.0.1:5000 ...
echo.
echo ===============================================================================
echo  Flask Main Store:  http://127.0.0.1:5000/
echo  PHP IP Lab Portal: http://127.0.0.1:8080/
echo ===============================================================================
echo.

python app.py

pause
