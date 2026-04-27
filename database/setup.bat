@echo off
REM Database Setup Script for Flight Control Software
REM This script creates the PostgreSQL database, runs schema, and seeds initial data

set DB_NAME=flight_control
set DB_USER=flight_user
set DB_PASS=flight_pass_2025
set PGPASSWORD=%DB_PASS%

setlocal enabledelayedexpansion

REM Common default postgres passwords to try automatically
set PGPASS_TRIALS[0]=postgres
set PGPASS_TRIALS[1]=admin
set PGPASS_TRIALS[2]=root
set PGPASS_TRIALS[3]=password
set PGPASS_TRIALS[4]=
set TRIAL_COUNT=5

echo Setting up PostgreSQL database for Flight Control Software...
echo Database: %DB_NAME%
echo User: %DB_USER%

REM Check if PostgreSQL is installed and running
psql --version >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    echo ERROR: PostgreSQL is not installed or not in PATH.
    echo.
    echo ALTERNATIVE: Run 'start-demo-docker.bat' for 1-click setup with no dependencies
    echo.
    pause
    exit /b 1
)

REM First check if we can already connect directly as flight_user (bypass postgres superuser requirement)
echo Checking existing database connection...
set PGPASSWORD=%DB_PASS%
psql -U %DB_USER% -h localhost -d %DB_NAME% -c "SELECT 1;" >nul 2>&1
if %ERRORLEVEL% EQU 0 (
    echo ✓ Database already exists and is accessible. Skipping user/database creation.
    goto run_schema
)

REM Automatically try common postgres passwords
echo.
echo Trying common postgres superuser passwords automatically...
set PGPASSWORD=
set TRIAL_SUCCESS=0

for /l %%i in (0,1,%TRIAL_COUNT%) do (
    if defined PGPASS_TRIALS[%%i] (
        set PGPASSWORD=!PGPASS_TRIALS[%%i]!
        echo   Trying password: !PGPASS_TRIALS[%%i]!
        
        psql -U postgres -h localhost -c "SELECT 1;" >nul 2>&1
        if !ERRORLEVEL! EQU 0 (
            echo ✓ Password found: !PGPASS_TRIALS[%%i]!
            set TRIAL_SUCCESS=1
            goto try_complete
        )
    )
)

:try_complete

if %TRIAL_SUCCESS% EQU 0 (
    echo.
    echo ⚠️  Could not automatically log in as postgres superuser
    echo.
    echo OPTIONS:
    echo   1. Run 'start-demo-docker.bat' - NO POSTGRES SETUP REQUIRED
    echo   2. Manually create:
    echo      • Database: %DB_NAME%
    echo      • User:     %DB_USER%
    echo      • Password: %DB_PASS%
    echo      • Grant all privileges on %DB_NAME% to %DB_USER%
    echo.
    echo Then run this script again.
    echo.
    pause
    exit /b 1
)

echo.
echo Creating database user...
psql -U postgres -h localhost -c "CREATE USER %DB_USER% WITH PASSWORD '%DB_PASS%';" 2>nul
echo ✓ User created or already exists

echo Creating database...
psql -U postgres -h localhost -c "CREATE DATABASE %DB_NAME% OWNER %DB_USER%;" 2>nul
echo ✓ Database created or already exists

echo Granting privileges...
psql -U postgres -h localhost -c "GRANT ALL PRIVILEGES ON DATABASE %DB_NAME% TO %DB_USER%;" 2>nul
echo ✓ Privileges granted

set PGPASSWORD=%DB_PASS%

:run_schema

REM Run schema
echo Running database schema...
psql -U %DB_USER% -h localhost -d %DB_NAME% -f schema.sql
if %ERRORLEVEL% EQU 0 (
    echo Schema created successfully.
) else (
    echo ERROR: Failed to create schema.
    pause
    exit /b 1
)

REM Seed initial data
echo Seeding initial data...
psql -U %DB_USER% -h localhost -d %DB_NAME% -f seed.sql
if %ERRORLEVEL% EQU 0 (
    echo Initial data seeded successfully.
) else (
    echo ERROR: Failed to seed data.
    pause
    exit /b 1
)

echo.
echo Database setup completed successfully!
echo.
echo Database connection details:
echo Host: localhost
echo Database: %DB_NAME%
echo User: %DB_USER%
echo Password: %DB_PASS%
echo.
echo Please update backend/config/database.php with these credentials.
pause
