@echo off
REM Database Backup Script for Flight Control Software
REM Usage: backup.bat [database_name] [username] [output_file]

set DB_NAME=%1
if "%DB_NAME%"=="" set DB_NAME=flight_control

set DB_USER=%2
if "%DB_USER%"=="" set DB_USER=postgres

set OUTPUT_FILE=%3
if "%OUTPUT_FILE%"=="" set OUTPUT_FILE=backup_%DATE:~-4,4%%DATE:~-10,2%%DATE:~-7,2%_%TIME:~0,2%%TIME:~3,2%%TIME:~6,2%.sql

echo Backing up database %DB_NAME% to %OUTPUT_FILE%

REM Create backups directory if it doesn't exist
if not exist "backups" mkdir backups

REM Perform the backup using pg_dump
pg_dump -U %DB_USER% -h localhost -d %DB_NAME% -f backups/%OUTPUT_FILE% --no-password --format=custom

if %ERRORLEVEL% EQU 0 (
    echo Backup completed successfully: backups/%OUTPUT_FILE%
    REM Log successful backup
    echo %DATE% %TIME% - Backup successful: %OUTPUT_FILE% >> backup.log
) else (
    echo Backup failed!
    echo %DATE% %TIME% - Backup failed >> backup.log
)

REM Optional: Keep only last 7 backups
for /f "skip=7" %%i in ('dir /b /o-d backups\*.sql') do del backups\%%i
