@echo off
REM Flight Control System - Demo Environment Setup Script

echo ============================================
echo   Flight Control System - Demo Setup
echo ============================================
echo.
echo TIP: This is the native local demo.
echo For 1-click zero setup containerized version run: start-demo-docker.bat
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
echo Generating Demo Data...
echo ============================================
echo.

REM Generate demo data
cd database
php demo-data-generator.php run
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Demo data generation failed.
    cd ..
    pause
    exit /b 1
)
cd ..

echo.
echo ============================================
echo Starting Demo Environment...
echo ============================================
echo.

REM Start backend server
echo Starting Backend Server (PHP)...
start "Flight Control Backend" cmd /c "cd backend && start-server.bat"

echo.
echo Waiting 5 seconds for backend to initialize...
timeout /t 5 /nobreak >nul

echo.
echo ============================================
echo Starting Frontend Server (Vite)...
echo ============================================
start "Flight Control Frontend" cmd /c "cd frontend && start-frontend.bat"

echo.
echo ============================================
echo Starting WebSocket Server...
echo ============================================
start "Flight Control WebSocket" cmd /c "cd backend && php websocket-server.php"

echo.
echo ============================================
echo Demo Environment Setup Complete!
echo ============================================
echo.
echo DEMO ACCESS CREDENTIALS:
echo.
echo   Administrator:
echo   * Username: admin
echo   * Password: FlightControl@2026!
echo   * URL: http://localhost:5173
echo.
echo   Air Traffic Controller:
echo   * Username: atc_demo
echo   * Password: Controller2026
echo   * URL: http://localhost:5173
echo.
echo   System Observer:
echo   * Username: observer
echo   * Password: DemoObserver2026
echo   * URL: http://localhost:5173
echo.
echo   Demo Passengers:
echo   * Username: passenger1 to passenger50
echo   * Password: pass123
echo   * URL: http://localhost:5173
echo.
echo DEMO FEATURES:
echo   * 100+ flights across 12 airports
echo   * 500+ passenger profiles
echo   * 300+ bookings with real scenarios
echo   * All 12 modules with demo data
echo   * Real-time flight updates
echo   * Interactive dashboards
echo   * PWA features (self-checkin, baggage tracking)
echo.
echo API ENDPOINTS:
echo   * Backend API: http://localhost:8000
echo   * WebSocket: ws://localhost:8080
echo   * Frontend: http://localhost:5173
echo.
echo QUICK START GUIDE:
echo   1. Open http://localhost:5173 in your browser
echo   2. Login with admin / FlightControl@2026!
echo   3. Explore the dashboard and modules
echo   4. Try passenger login with passenger1/pass123
echo   5. Check real-time flight updates
echo.
echo NOTE: This is a demo environment with sample data.
echo     All servers are running in the background.
echo.
echo Press any key to close this window.
echo The demo environment will continue running.
echo.

pause >nul