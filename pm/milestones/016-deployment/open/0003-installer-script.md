# 0003 — Installer-Script

## Goal
Ein Script (`scripts/install.sh`), das die Bashrc-Integration auf einem
frischen Rechner einrichtet — den Snippet-Block idempotent in die
Login-Shell-Config eintraegt und dem User die naechsten Schritte
ausgibt.

## Notes
- Idempotenz ist Pflicht: zweimal ausfuehren darf den Block nicht
  duplizieren. Detection ueber den Marker-Kommentar aus 0002.
- Erkennt aktiv genutzte Shell (zsh vs bash) ueber `$SHELL` und
  schreibt in die richtige Config-Datei. Wenn unklar → fragen, nicht
  raten.
- Repo-URL nicht hartkodieren — User-Eingabe oder Default
  (`<wirkliche Repo-URL>` als Konstante an einer Stelle).
- Niemals ohne Bestaetigung in `.bashrc`/`.zshrc` schreiben — Default
  ist Diff anzeigen + Ja/Nein.
- Script darf **nicht** automatisch Speckig starten — User entscheidet
  ueber den Setup-Tab, ob alles sitzt.
- Pre-flight: pruefen, ob PHP 8.5+ vorhanden ist; sonst klare
  Fehlermeldung mit Hinweis.

## Done when
- `scripts/install.sh` existiert, ist `+x`, und laeuft auf Linux und
  Mac.
- Zweifacher Lauf erzeugt nur einen Snippet-Block in der Config-Datei.
- Bei fehlendem PHP 8.5+ → klare Meldung, kein Schreiben in die
  Config.
- Nach erfolgreichem Lauf druckt das Script die naechsten Schritte
  (Shell neu laden, `speckig` aufrufen, Browser auf 8083).

## Out of scope
- Auto-clone des Repos (User clont selbst).
- Update-Pfad (`speckig update`).
- Systemd / launchd.
- Windows.

See: pm/how-to/process.md

## Plan
- **Neue Datei**: `scripts/install.sh`, `chmod +x`.
- **Shebang**: `#!/usr/bin/env bash`, `set -euo pipefail`.
- **Schritte**:
  1. **PHP-Check**: `command -v php` → falls fehlt: klare Meldung
     ("Bitte PHP 8.5+ installieren, siehe pm/how-to/install.md"),
     exit 1.
  2. **PHP-Version**: `php -r 'exit(PHP_VERSION_ID >= 80500 ? 0 : 1);'`
     → falls fail: Meldung mit gefundener Version, exit 1.
  3. **Shell erkennen**: `case "$SHELL" in */zsh) target=~/.zshrc;;
     */bash) target=~/.bashrc;; *) target="";; esac`. Wenn leer →
     fragen (interaktiv): "Konnte Shell nicht erkennen ($SHELL).
     In welche Datei eintragen?", User-Input lesen. Default
     `~/.bashrc`.
  4. **Marker-Check**: `grep -q "^# >>> speckig" "$target"` → wenn
     ja: melden "Snippet schon eingerichtet (Marker gefunden in
     $target). Nichts geaendert.", exit 0.
  5. **Diff zeigen + Bestaetigung**: `echo "Folgender Block wird in
     $target eingefuegt:"; cat scripts/bashrc-snippet.sh; read -p
     "OK? [y/N] " ans`. Bei `y/Y` weiter, sonst exit 0.
  6. **Append**: `printf '\n' >> "$target"; cat
     scripts/bashrc-snippet.sh >> "$target"`. Atomar genug (ein
     append, der entweder ganz oder gar nicht passiert).
  7. **Next-Steps drucken**: "Bitte `source $target` ausfuehren (oder
     Shell neu starten). Danach `speckig` aufrufen."
- **Idempotenz**: Marker-Check (Schritt 4) sorgt dafuer, dass zweimal
  Ausfuehren keine Duplikate erzeugt.
- **Repo-URL nicht hartkodieren**: das Script geht davon aus, dass
  es **innerhalb** eines bereits geklonten Repos lebt. Es macht keinen
  `git clone` — das ist Userin-Sache.
- **Spec-Block** als Kommentar oben im Script: Vertrag, Idempotenz,
  was es nicht macht.
- **Files touched**: `scripts/install.sh` (neu).

## Verifikation
- `bash -n scripts/install.sh` clean.
- `shellcheck scripts/install.sh` ohne Errors (Warnings ok).
- Trockenlauf in einer Sandbox-Shell:
  - `cp ~/.bashrc /tmp/bashrc.bak`
  - `HOME=/tmp/fakehome SHELL=/bin/bash bash scripts/install.sh`
    (interaktiv: y bestaetigen).
  - `grep -c "^# >>> speckig$" /tmp/fakehome/.bashrc` → 1.
  - Zweiter Lauf: `HOME=/tmp/fakehome SHELL=/bin/bash bash
    scripts/install.sh` → meldet "schon eingerichtet", exit 0.
  - `grep -c "^# >>> speckig$" /tmp/fakehome/.bashrc` → immer noch 1.
  - Cleanup: `rm -rf /tmp/fakehome`.
- PHP-Fail-Test: `PATH= bash scripts/install.sh` → "Bitte PHP
  installieren", exit 1.
- `git status` clean.

## Out of scope (Plan)
- Auto-clone des Repos.
- `speckig update`.
- Systemd / launchd.
