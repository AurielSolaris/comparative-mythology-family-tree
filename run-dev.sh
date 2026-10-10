#!/usr/bin/env bash
#
# Start the local development environment (Laravel server, queue, logs and Vite).
# Bootstraps anything missing on first run: Composer/Bun dependencies, .env,
# app key, SQLite database and migrations. Extra arguments are passed to
# `php artisan dev` (e.g. ./run-dev.sh --stream).

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

info() { printf '\033[1;34m==>\033[0m %s\n' "$1"; }
fail() { printf '\033[1;31merror:\033[0m %s\n' "$1" >&2; exit 1; }

require() {
    command -v "$1" >/dev/null 2>&1 || fail "'$1' is not installed or not on PATH. $2"
}

require php "Install PHP 8.5+ (https://www.php.net/downloads)."

# Windows installs (e.g. Herd) may only ship composer.bat, which Git Bash won't resolve as `composer`.
composer=composer
if ! command -v composer >/dev/null 2>&1 && command -v composer.bat >/dev/null 2>&1; then
    composer=composer.bat
fi
require "$composer" "Install Composer (https://getcomposer.org/download/)."
require bun "Install Bun (https://bun.sh)."

php -r '
    $ini = php_ini_loaded_file() ?: "(no php.ini loaded)";
    $missing = array_diff(["openssl", "pdo_sqlite", "mbstring", "fileinfo", "curl"], get_loaded_extensions());
    if ($missing) {
        echo "error: PHP is missing extensions: ", implode(", ", $missing), PHP_EOL;
        echo "Uncomment the matching extension=... lines in $ini", PHP_EOL;
        exit(1);
    }
' 2>/dev/null || exit 1

if [ ! -f vendor/autoload.php ]; then
    info "Installing Composer dependencies"
    "$composer" install --no-interaction
fi

if [ ! -d node_modules ]; then
    info "Installing Bun dependencies"
    bun install --ignore-scripts
fi

if [ ! -f .env ]; then
    info "Creating .env from .env.example"
    cp .env.example .env
fi

if ! grep -qE '^APP_KEY=.+' .env; then
    info "Generating application key"
    php artisan key:generate --no-interaction
fi

if [ ! -f database/database.sqlite ]; then
    info "Creating SQLite database"
    touch database/database.sqlite
fi

info "Running migrations"
php artisan migrate --no-interaction

info "Starting dev servers (Ctrl+C to stop)"
exec "$composer" run dev -- "$@"
