@echo off
REM Flight Control Backend Server Startup Script

echo Starting Flight Control Backend Server...
echo.

REM Check if PHP is installed
php --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: PHP is not installed or not in PATH.
    echo Please install PHP and ensure it's in your PATH.
    pause
    exit /b 1
)

REM Check if database is configured
if not exist "config\database.php" (
    echo ERROR: Database configuration not found.
    echo Please run database\setup.bat first to configure the database.
    pause
    exit /b 1
)

REM Start PHP built-in server
echo Starting PHP development server on http://localhost:8000
echo Press Ctrl+C to stop the server
echo.

cd /d "%~dp0"
php -S localhost:8000 -t api/

pause
