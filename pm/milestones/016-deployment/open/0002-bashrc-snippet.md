# 0002 — Bashrc/Zshrc-Snippet

## Goal
Es gibt ein klar abgegrenztes Shell-Snippet, das in `~/.bashrc` bzw.
`~/.zshrc` eingetragen wird und eine `speckig`-Funktion bereitstellt,
die den Server mit korrektem docroot und Port startet.

## Notes
- POSIX-sh-kompatibel halten, damit bash und zsh beide laufen — keine
  bash-only-Konstrukte wie `[[ ]]`.
- Snippet muss durch einen Marker-Kommentar eingerahmt sein
  (`# >>> speckig` / `# <<< speckig`), damit der Installer (0003)
  idempotent re-installieren und sauber wieder entfernen kann.
- Funktion `speckig` muss den Default-Port 8083 nutzen und `-t app`
  setzen (siehe Memory). Optionales erstes Argument als alternativer
  Repo-Pfad, analog zu `scripts/run.sh`.
- Snippet darf den Server nicht beim Shell-Start anwerfen, nur eine
  Funktion definieren.
- SPECKIG_ROOT korrekt exportieren, sonst stimmt der Tree-Pfad nicht.

## Done when
- Snippet existiert als versionierte Datei im Repo, lesbar fuer den
  Installer (z.B. `scripts/bashrc-snippet.sh`).
- Eintrag in eine leere `.bashrc` per `eval "$(cat ...)"` macht
  `speckig` als Shell-Funktion verfuegbar.
- `speckig` ohne Argument startet den Server gegen das Repo, in dem
  das Snippet ausgepackt wurde.
- `speckig /pfad/zu/anderem/repo` startet gegen jenes Repo.
- Marker-Kommentare oben und unten klar erkennbar.

## Out of scope
- Installer-Script selbst (0003).
- Self-Update (`speckig update`).
- Windows-Support.

See: CLAUDE.md

## Plan
- **Neue Datei**: `scripts/bashrc-snippet.sh`.
- **Inhalt** (POSIX-sh-kompatibel):
  ```sh
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
  ```
- **Marker**: `# >>> speckig` / `# <<< speckig` als Block-Begrenzer
  fuer den Installer (0003).
- **Snippet definiert nur eine Funktion** — kein Auto-Start beim
  Shell-Login.
- **Mac/Linux-Kompatibilitaet**: Browser-Open faellt von `xdg-open`
  zurueck auf `open` (macOS). Beide Plattformen werden so abgedeckt.
- **Port 8083**: hart kodiert wie in `scripts/run.sh`. (Memory: User
  laeuft seinen eigenen Server auf 8083 — die Funktion ist fuer
  **fremde** Maschinen gedacht, dort ist 8083 frei.)
- **Spec-Kommentar** als sh-Kommentarzeile oben in der Datei, der das
  Vertrag dokumentiert (was die Funktion tut, dass Marker-Kommentare
  Installer-Pflicht sind).
- **Files touched**: `scripts/bashrc-snippet.sh` (neu, ohne `+x` —
  wird gesourced, nicht ausgefuehrt).

## Verifikation
- Datei existiert.
- `bash -n scripts/bashrc-snippet.sh` clean (Syntax).
- `sh -c '. ./scripts/bashrc-snippet.sh && type speckig'` zeigt die
  Funktion als definiert.
- Manueller Smoketest auf der Dev-Maschine:
  - `( . scripts/bashrc-snippet.sh && speckig /tmp/fake )` → klare
    Fehlermeldung "kein app/-Verzeichnis", `return 1`.
  - **Nicht** mit dem echten Repo testen, weil das den 8083-User-
    Server-Konflikt triggert. Stattdessen das Snippet rein syntaktisch
    pruefen.
- Marker `# >>> speckig` und `# <<< speckig` als erste/letzte Zeile
  des Funktionsblocks vorhanden (`grep -c "^# >>> speckig$"
  scripts/bashrc-snippet.sh` → 1).
- `git status` clean.

## Out of scope (Plan)
- Installer-Script (0003).
- Konfigurierbarer Port via Argument.
- Windows.
