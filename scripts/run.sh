#!/usr/bin/env bash
set -euo pipefail

# Lokales Speckig starten: php -S auf 8083 + Default-Browser oeffnen.
# Aufruf: ./scripts/run.sh

repo_root="$(cd "$(dirname "$0")/.." && pwd)"
cd "$repo_root/app"

port=8083
url="http://localhost:${port}/"

# Browser asynchron oeffnen, kurz warten bis Server hochkommt.
( sleep 0.5 && xdg-open "$url" >/dev/null 2>&1 ) &

exec php -S "localhost:${port}" -t .
