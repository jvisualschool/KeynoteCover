#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."
for file in app/*.php public/*.php tests/*_test.php; do php -l "$file" >/dev/null; done
for file in public/assets/*.js tests/*.cjs; do node --check "$file"; done
for test in tests/*_test.php; do php "$test"; done
node tests/appearance_test.cjs
node tests/download_guard.cjs
python3 tests/security_http.py
