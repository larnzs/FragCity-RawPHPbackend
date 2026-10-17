@echo off
setlocal EnableExtensions
title FragCity - Update www from GitHub

cd /d "%~dp0"
echo.
echo ===============================================
echo  FragCity www - Update from GitHub
echo ===============================================
echo.

git rev-parse --is-inside-work-tree >nul 2>&1
if errorlevel 1 (
    echo [ERROR] This file must be inside the root of the Git repository.
    goto :fail
)

set "REMOTE_URL="
for /f "delims=" %%R in ('git remote get-url origin 2^>nul') do set "REMOTE_URL=%%R"
echo %REMOTE_URL% | findstr /i /c:"larnzs/FragCity-RawPHPbackend" >nul
if errorlevel 1 (
    echo [ERROR] The origin remote is not FragCity-RawPHPbackend.
    echo Current origin: %REMOTE_URL%
    goto :fail
)

set "CURRENT_BRANCH="
for /f "delims=" %%B in ('git branch --show-current') do set "CURRENT_BRANCH=%%B"
if /i not "%CURRENT_BRANCH%"=="www" (
    echo [ERROR] Current branch is "%CURRENT_BRANCH%", not "www".
    echo This updater only updates the www branch.
    goto :fail
)

set "DIRTY="
for /f "delims=" %%S in ('git status --porcelain --untracked-files^=normal') do set "DIRTY=1"
if defined DIRTY (
    echo [ERROR] Local changes or untracked files were found.
    echo First review them and run upload-changes.bat, or back them up and handle them manually.
    echo This script will not overwrite local work.
    git status --short
    goto :fail
)

echo [INFO] Fetching latest commits from origin...
git fetch origin
if errorlevel 1 goto :fail

echo [INFO] Fast-forwarding local www branch...
git pull --ff-only origin www
if errorlevel 1 (
    echo [ERROR] Fast-forward update failed. No force push/reset was attempted.
    echo Review local and remote history before trying again.
    goto :fail
)

echo.
echo [SUCCESS] Local www is updated to the latest GitHub version.
git log -1 --oneline
goto :end

:fail
echo.
echo [FAILED] No local changes were discarded.
:end
echo.
pause
endlocal
