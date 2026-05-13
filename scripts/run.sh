#!/usr/bin/env bash
set -euo pipefail

# Lokales Speckig starten: php -S auf 8083 + Default-Browser oeffnen.
# Aufruf:
#   ./scripts/run.sh                       -> eigenes Repo browsen (Default).
#   ./scripts/run.sh /pfad/zu/anderem/repo -> fremdes Repo browsen.
#
# Speckig-Code lebt immer unter $speckig_app_dir (DocRoot von php -S);
# $speckig_root ist der Repo-Root, dessen Inhalt im Tree erscheint.

speckig_app_dir="$(cd "$(dirname "$0")/../app" && pwd)"

arg_root="${1:-}"

if [ -n "$arg_root" ]
then
    if [ ! -d "$arg_root" ]
    then
        echo "run.sh: '$arg_root' ist kein Verzeichnis" >&2
        exit 1
    fi
    speckig_root="$(cd "$arg_root" && pwd)"
else
    speckig_root="$(cd "$speckig_app_dir/.." && pwd)"
fi

export SPECKIG_ROOT="$speckig_root"

cd "$speckig_app_dir"

port=8083
url="http://localhost:${port}/"

# Browser asynchron oeffnen, kurz warten bis Server hochkommt.
( sleep 0.5 && xdg-open "$url" >/dev/null 2>&1 ) &

exec php -S "localhost:${port}" -t .
