@echo off
rem Build the frontend assets for production into public\build.
rem Installs Bun dependencies from the lockfile, type-checks, then runs Vite.

setlocal EnableExtensions
cd /d "%~dp0"

where bun >nul 2>&1 || (echo error: 'bun' is not installed or not on PATH. Install Bun ^(https://bun.sh^). & exit /b 1)

echo ==^> Installing Bun dependencies
call bun install --frozen-lockfile --ignore-scripts || exit /b 1

echo ==^> Type-checking
call bun run typecheck || exit /b 1

echo ==^> Building assets
call bun run build || exit /b 1

echo ==^> Build complete: public\build
