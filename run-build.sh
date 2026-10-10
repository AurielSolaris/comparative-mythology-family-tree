#!/usr/bin/env bash
#
# Build the frontend assets for production into public/build.
# Installs Bun dependencies from the lockfile, type-checks, then runs Vite.

set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")"

info() { printf '\033[1;34m==>\033[0m %s\n' "$1"; }
fail() { printf '\033[1;31merror:\033[0m %s\n' "$1" >&2; exit 1; }

command -v bun >/dev/null 2>&1 || fail "'bun' is not installed or not on PATH. Install Bun (https://bun.sh)."

info "Installing Bun dependencies"
bun install --frozen-lockfile --ignore-scripts

info "Type-checking"
bun run typecheck

info "Building assets"
bun run build

info "Build complete: public/build"
