@echo off
REM Flight Control Frontend Development Server Startup Script

echo Starting Flight Control Frontend Development Server...
echo.

REM Check if Node.js is installed
node --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Node.js is not installed or not in PATH.
    echo Please install Node.js and ensure it's in your PATH.
    pause
    exit /b 1
)

REM Check if npm is installed
npm --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: npm is not installed.
    echo Please install npm (usually comes with Node.js).
    pause
    exit /b 1
)

REM Check if node_modules exists, if not install dependencies
if not exist "node_modules" (
    echo Installing dependencies...
    npm install
    if %ERRORLEVEL% NEQ 0 (
        echo ERROR: Failed to install dependencies.
        pause
        exit /b 1
    )
)

REM Start Vite development server
echo Starting Vite development server on http://localhost:5173
echo Press Ctrl+C to stop the server
echo.

cd /d "%~dp0"
npm run dev

pause
