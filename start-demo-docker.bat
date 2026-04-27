@echo off
echo ============================================
echo   Flight Control System - 1-Click Demo
echo ============================================
echo.
echo Starting complete demo environment with Docker.
echo This will automatically setup database, backend, frontend and websocket.
echo No manual configuration required.
echo.

REM Check if docker is installed
docker --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: Docker Desktop is not installed.
    echo Please install Docker Desktop from https://www.docker.com/products/docker-desktop/
    echo.
    pause
    exit /b 1
)

REM Check if Docker Desktop engine is actually running
docker info >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo WARNING: Docker Desktop is not running.
    echo.
    echo Docker is required for the full containerized demo environment.
    echo.
    choice /c YN /m "Would you like to start the native non-Docker local demo instead?"
    if errorlevel 2 (
        echo.
        echo You can start Docker Desktop and run this script again when ready.
        echo.
        pause
        exit /b 1
    )
    if errorlevel 1 (
        echo.
        echo Starting native local demo environment...
        call start-demo.bat
        exit /b 0
    )
)

REM Check if demo is already running
docker compose -f docker-compose.demo.yml ps --services --filter "status=running" | findstr /r "postgres backend frontend websocket" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo NOTICE: Demo environment is already running.
    echo.
    echo If you want to restart it first run:
    echo   docker compose -f docker-compose.demo.yml down
    echo.
    pause
    goto :open_browser
)

echo Starting containers...
docker compose -f docker-compose.demo.yml up -d --wait

if %ERRORLEVEL% NEQ 0 (
    echo.
    echo ERROR: Failed to start demo environment.
    echo.
    echo Check container logs with:
    echo   docker compose -f docker-compose.demo.yml logs -f
    echo.
    pause
    exit /b 1
)

REM Verify all containers are running
echo.
echo Verifying system health...
timeout /t 3 /nobreak >nul

echo.
echo ============================================
echo Demo Environment Ready!
echo ============================================
echo.
echo DEMO ACCESS:
echo.
echo   Administrator:
echo   * Username: admin
echo   * Password: FlightControl@2026!
echo   * URL: http://localhost:5173
echo.
echo   Air Traffic Controller:
echo   * Username: atc_demo
echo   * Password: Controller2026
echo.
echo   System Observer:
echo   * Username: observer
echo   * Password: DemoObserver2026
echo.
echo RUNNING SERVICES:
echo   * Frontend:        http://localhost:5173
echo   * Backend API:     http://localhost:8000
echo   * WebSocket:       ws://localhost:8080
echo   * Database:        localhost:5432
echo.
echo DATABASE CONNECTION:
echo   * Database: flight_control
echo   * Username: flight_user
echo   * Password: flight_pass_2025
echo.
echo USAGE COMMANDS:
echo   * Stop demo:       docker compose -f docker-compose.demo.yml down
echo   * Stop + reset:    docker compose -f docker-compose.demo.yml down -v
echo   * View logs:       docker compose -f docker-compose.demo.yml logs -f
echo.
echo NOTE: First run may take 30-60 seconds while database initializes.
echo.

:open_browser
echo Press any key to open demo in browser...
pause >nul

start http://localhost:5173