# scripts/bashrc-snippet.sh — Vertrag:
#
# POSIX-sh-kompatibles Snippet, das in `~/.bashrc` oder `~/.zshrc`
# gesourced wird. Definiert die Shell-Funktion `speckig`, die den
# lokalen PHP-Built-in-Server fuer Speckig startet.
#
# Diese Datei wird **gesourced**, nicht ausgefuehrt — bewusst kein
# Shebang und kein +x-Bit. Sie definiert nur eine Funktion und startet
# beim Source-Vorgang nichts.
#
# Die Marker-Kommentare `# >>> speckig` und `# <<< speckig` rahmen den
# Funktionsblock. `scripts/install.sh` (M016/0003) nutzt sie, um den
# Block idempotent einzufuegen bzw. wieder zu entfernen. Marker NICHT
# umbenennen ohne den Installer mitzuziehen.
#
# Verhalten der Funktion:
# - `speckig`                  -> Server gegen `$HOME/Desktop/speckig`.
# - `speckig /pfad/zu/repo`    -> Server gegen diesen Pfad.
# - Exportiert `SPECKIG_ROOT`, damit der Tree den Repo-Root findet.
# - Startet `php -S localhost:8083 -t .` aus `<repo>/app`
#   (Memory: `php -S` braucht `-t app`).
# - Oeffnet asynchron den Default-Browser via `xdg-open` (Linux) bzw.
#   `open` (macOS).

# >>> speckig (managed by scripts/install.sh)
speckig() {
    local repo_root
    if [ -n "$1" ]; then
        repo_root="$1"
    else
        repo_root="$HOME/Desktop/speckig"
    fi
    if [ ! -d "$repo_root/app" ]; then
        echo "speckig: kein app/-Verzeichnis unter $repo_root" >&2
        return 1
    fi
    export SPECKIG_ROOT="$repo_root"
    local url="http://localhost:8083/"
    ( sleep 0.5 && ( command -v xdg-open >/dev/null && xdg-open "$url" \
        || ( command -v open >/dev/null && open "$url" ) ) >/dev/null 2>&1 ) &
    ( cd "$repo_root/app" && php -S "localhost:8083" -t . )
}
# <<< speckig
