@echo off
setlocal EnableExtensions
title FragCity - Upload local changes to GitHub

cd /d "%~dp0"
echo.
echo ===============================================
echo  FragCity www - Upload local changes
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
    if /i "%CURRENT_BRANCH%"=="flute-base" (
        echo [INFO] Preparing the www development branch...
        git fetch origin
        if errorlevel 1 goto :fail

        git show-ref --verify --quiet refs/heads/www
        if not errorlevel 1 (
            git switch www
            if errorlevel 1 (
                echo [ERROR] Could not switch to local branch www.
                echo Check your local changes and resolve the checkout issue manually.
                goto :fail
            )
        ) else (
            git show-ref --verify --quiet refs/remotes/origin/www
            if not errorlevel 1 (
                git switch --track -c www origin/www
                if errorlevel 1 (
                    echo [ERROR] Could not create local branch www tracking origin/www.
                    echo Check your local changes; this script has not discarded them.
                    goto :fail
                )
            ) else (
                echo [ERROR] The remote origin/www branch does not exist.
                echo Create it on GitHub or fetch the correct repository first.
                goto :fail
            )
        )
    ) else (
        echo [ERROR] Current branch is "%CURRENT_BRANCH%", not "www".
        echo Switch to www before uploading. No files were changed by this script.
        goto :fail
    )
)

git fetch origin
if errorlevel 1 goto :fail

git show-ref --verify --quiet refs/remotes/origin/www
if errorlevel 1 (
    echo [ERROR] origin/www was not found after fetch.
    goto :fail
)

git diff --cached --quiet
if errorlevel 1 (
    echo [ERROR] You already have staged changes.
    echo Review them and commit them manually, or unstage them intentionally before retrying.
    goto :fail
)

git merge-base --is-ancestor origin/www HEAD
if errorlevel 1 (
    echo [ERROR] GitHub www has commits that are not in your local branch.
    echo Run update.bat first after safely handling local changes, then retry.
    goto :fail
)

echo.
echo [INFO] Current local changes:
git status --short
echo.
echo [INFO] Staging changes...
git add -A
if errorlevel 1 goto :fail

echo [INFO] Checking staged filenames for common secret/runtime files...
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference='Stop'; $p=@(git diff --cached --name-only); $bad=@($p | Where-Object { $n=$_ -replace '\\','/'; (($n -match '(^|/)\.env($|\.)') -and ($n -notmatch '(^|/)\.env\.example$')) -or ($n -match '(^|/)(settings\.json|.*\.(sql|sqlite|db|dump|bak|log))$') -or ($n -match '(^|/)storage/(logs|cache|backup|app/cache)(/|$)') -or ($n -match '(^|/)config/(database|secrets?|local)(\.[^/]+)?$') }); if($bad.Count -gt 0){ Write-Output 'BLOCKED: review these sensitive/runtime paths before committing:'; $bad | ForEach-Object { Write-Output ('  ' + $_) }; exit 17 }"
if errorlevel 1 (
    echo.
    echo [SAFETY] Suspected secret/runtime files were detected.
    echo The changes have been unstaged but NOT deleted. Review them, update .gitignore if appropriate, then retry.
    git reset
    goto :fail
)

git diff --cached --quiet
if not errorlevel 1 (
    echo [INFO] No staged changes found. The working tree is already up to date.
    goto :success
)

echo.
git diff --cached --stat
echo.
set "COMMIT_MESSAGE="
set /p "COMMIT_MESSAGE=Commit message (Enter for default): "
if not defined COMMIT_MESSAGE set "COMMIT_MESSAGE=chore(www): upload local FragCity changes"

git commit -m "%COMMIT_MESSAGE%"
if errorlevel 1 (
    echo [ERROR] Commit failed. Review the message and Git output.
    goto :fail
)

echo.
echo [INFO] Pushing branch www to GitHub...
git push origin www
if errorlevel 1 (
    echo [ERROR] Push failed. Your local commit has been kept.
    echo Do not use force push. Review the Git error, then run update.bat if it is safe.
    goto :fail
)

:success
echo.
echo [SUCCESS] Local www changes have been uploaded to GitHub.
goto :end

:fail
echo.
echo [FAILED] No local files were deleted by this script.
echo Check the messages above before retrying.

:end
echo.
pause
endlocal
