@echo off
rem Start the local development environment (Laravel server, queue, logs and Vite).
rem Bootstraps anything missing on first run: Composer/Bun dependencies, .env,
rem app key, SQLite database and migrations. Extra arguments are passed to
rem `php artisan dev` (e.g. run-dev.bat --stream).

setlocal EnableExtensions
cd /d "%~dp0"

where php >nul 2>&1 || (echo error: 'php' is not installed or not on PATH. Install PHP 8.5+ ^(https://www.php.net/downloads^). & exit /b 1)
where composer >nul 2>&1 || (echo error: 'composer' is not installed or not on PATH. Install Composer ^(https://getcomposer.org/download/^). & exit /b 1)
where bun >nul 2>&1 || (echo error: 'bun' is not installed or not on PATH. Install Bun ^(https://bun.sh^). & exit /b 1)

php -r "$ini = php_ini_loaded_file() ?: '(no php.ini loaded)'; $missing = array_diff(['openssl', 'pdo_sqlite', 'mbstring', 'fileinfo', 'curl'], get_loaded_extensions()); if ($missing) { echo 'error: PHP is missing extensions: ', implode(', ', $missing), PHP_EOL, 'Uncomment the matching extension=... lines in ', $ini, PHP_EOL; exit(1); }" 2>nul || exit /b 1

if not exist "vendor\autoload.php" (
    echo ==^> Installing Composer dependencies
    call composer install --no-interaction || exit /b 1
)

if not exist "node_modules\" (
    echo ==^> Installing Bun dependencies
    call bun install --ignore-scripts || exit /b 1
)

if not exist ".env" (
    echo ==^> Creating .env from .env.example
    copy /y ".env.example" ".env" >nul || exit /b 1
)

findstr /r /c:"^APP_KEY=..*" ".env" >nul || (
    echo ==^> Generating application key
    php artisan key:generate --no-interaction || exit /b 1
)

if not exist "database\database.sqlite" (
    echo ==^> Creating SQLite database
    type nul > "database\database.sqlite" || exit /b 1
)

echo ==^> Running migrations
php artisan migrate --no-interaction || exit /b 1

echo ==^> Starting dev servers (Ctrl+C to stop)
call composer run dev -- %*
exit /b %ERRORLEVEL%
