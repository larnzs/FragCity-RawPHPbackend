@echo off
setlocal EnableExtensions
title FragCity - Update Local Repository
set "PAUSE_ON_EXIT=1"
if /I "%~1"=="--no-pause" set "PAUSE_ON_EXIT=0"
set "RC=1"

cd /d "%~dp0"
if errorlevel 1 (
    echo ERROR: Could not enter the repository directory.
    goto :finish
)

where git >nul 2>&1
if errorlevel 1 (
    echo ERROR: Git was not found in PATH. Install Git for Windows first.
    goto :finish
)

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo ERROR: This folder is not a Git working tree.
    goto :finish
)

set "DIRTY="
for /f "delims=" %%I in ('git status --porcelain 2^>nul') do set "DIRTY=1"
if defined DIRTY (
    echo ERROR: Local files have uncommitted or untracked changes.
    echo Commit, stash, or move those files before updating.
    echo No branch switch or pull was attempted.
    goto :finish
)

git switch main
if errorlevel 1 (
    goto :finish
)

git pull --ff-only origin main
set "RC=%ERRORLEVEL%"

:finish
echo.
if "%RC%"=="0" (
    echo Result: SUCCESS
) else (
    echo Result: FAILED (exit code %RC%)
)
if "%PAUSE_ON_EXIT%"=="1" (
    echo.
    echo Please press any key to exit . . .
    pause >nul
)
endlocal & exit /b %RC%
