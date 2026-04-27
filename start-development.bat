@echo off
REM Flight Control Development Environment Startup Script

echo ============================================
echo   Flight Control Software - Development
echo ============================================
echo.

REM Check if PostgreSQL database is set up
echo Checking database setup...
if not exist "database\schema.sql" (
    echo ERROR: Database schema not found.
    echo Please ensure all database files are present.
    pause
    exit /b 1
)

REM Start database setup if needed
echo Starting database setup...
cd database
call setup.bat
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Database setup failed.
    cd ..
    pause
    exit /b 1
)
cd ..

echo.
echo ============================================
echo Starting Backend Server (PHP)...
echo ============================================
start "Flight Control Backend" cmd /c "cd backend && start-server.bat"

echo.
echo Waiting 3 seconds for backend to initialize...
timeout /t 3 /nobreak >nul

echo.
echo ============================================
echo Starting Frontend Server (Vite)...
echo ============================================
start "Flight Control Frontend" cmd /c "cd frontend && start-frontend.bat"

echo.
echo ============================================
echo Development servers are starting...
echo.
echo Backend API:  http://localhost:8000
echo Frontend App: http://localhost:5173
echo.
echo Press any key to close this window.
echo The servers will continue running in separate windows.
echo.

pause >nul
