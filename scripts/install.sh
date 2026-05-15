#!/usr/bin/env bash
set -euo pipefail

# scripts/install.sh — Vertrag:
#
# Richtet die Speckig-Shell-Integration auf einem frischen Rechner ein:
# traegt den Snippet-Block aus `scripts/bashrc-snippet.sh` in die
# Login-Shell-Config (`~/.bashrc` oder `~/.zshrc`) ein und druckt danach
# die naechsten Schritte.
#
# Idempotenz-Garantie: das Script erkennt einen bereits installierten
# Block ueber den Marker `# >>> speckig` (Start-Marker aus 0002). Zweite
# Ausfuehrung ist ein No-Op und bricht mit klarer Meldung ab.
#
# Pre-flight: prueft `php` im PATH und Version >= 8.5.0 (PHP_VERSION_ID
# >= 80500). Bei Fehlschlag: klare Meldung auf stderr, exit 1, ohne in
# die Shell-Config zu schreiben.
#
# Was es NICHT macht:
# - Kein `git clone` — Userin clont das Repo selbst.
# - Kein Auto-Start von Speckig — Userin ruft `speckig` selbst auf.
# - Kein Update-Pfad (`speckig update`).
# - Kein Systemd / launchd / Daemon.
# - Kein Windows-Support.
#
# Marker `# >>> speckig` / `# <<< speckig` werden mit dem Snippet aus
# 0002 geteilt — NICHT umbenennen ohne 0002 mitzuziehen.

script_dir="$(cd "$(dirname "$0")" && pwd)"
snippet_path="$script_dir/bashrc-snippet.sh"
marker_start='# >>> speckig'

err() {
    printf '%s\n' "$*" >&2
}

# 1. PHP vorhanden?
if ! command -v php >/dev/null 2>&1
then
    err "Bitte PHP 8.5+ installieren, siehe pm/how-to/install.md"
    exit 1
fi

# 2. PHP-Version >= 8.5
if ! php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);' >/dev/null 2>&1
then
    found_version="$(php -r 'echo PHP_VERSION;' 2>/dev/null || echo 'unbekannt')"
    err "PHP 8.5+ noetig, gefunden: $found_version"
    err "Siehe pm/how-to/install.md"
    exit 1
fi

# 3. Shell erkennen
target=""
case "${SHELL:-}" in
    */zsh)
        target="$HOME/.zshrc"
        ;;
    */bash)
        target="$HOME/.bashrc"
        ;;
    *)
        target=""
        ;;
esac

if [ -z "$target" ]
then
    printf 'Konnte Shell nicht erkennen (SHELL=%s).\n' "${SHELL:-}"
    printf 'In welche Datei eintragen? [Default: ~/.bashrc] '
    read -r user_target
    if [ -z "$user_target" ]
    then
        target="$HOME/.bashrc"
    else
        # Expand a leading ~ to $HOME without invoking eval.
        case "$user_target" in
            "~"|"~/"*)
                target="$HOME${user_target#\~}"
                ;;
            *)
                target="$user_target"
                ;;
        esac
    fi
fi

# Sicherstellen, dass Target existiert (touch ist idempotent).
touch "$target"

# 4. Marker-Check (Idempotenz).
if grep -q "^${marker_start}" "$target"
then
    printf 'Snippet schon eingerichtet (Marker gefunden in %s). Nichts geaendert.\n' "$target"
    exit 0
fi

# Sanity: Snippet-Datei vorhanden?
if [ ! -f "$snippet_path" ]
then
    err "Snippet-Datei nicht gefunden: $snippet_path"
    exit 1
fi

# 5. Diff zeigen + Bestaetigung.
printf 'Folgender Block wird in %s eingefuegt:\n\n' "$target"
printf -- '-----8<-----\n'
cat "$snippet_path"
printf -- '----->8-----\n\n'
printf 'OK? [y/N] '
read -r ans
case "$ans" in
    y|Y|yes|YES)
        ;;
    *)
        printf 'Abgebrochen, %s nicht geaendert.\n' "$target"
        exit 0
        ;;
esac

# 6. Append (Leerzeile + Snippet).
printf '\n' >> "$target"
cat "$snippet_path" >> "$target"

# 7. Next-Steps.
printf '\n'
printf 'Snippet in %s eingefuegt.\n' "$target"
printf '\n'
printf 'Naechste Schritte:\n'
printf '  1. Bitte `source %s` ausfuehren (oder Shell neu starten).\n' "$target"
printf '  2. Danach `speckig` aufrufen — startet den Server auf\n'
printf '     http://localhost:8083/ und oeffnet den Browser.\n'
