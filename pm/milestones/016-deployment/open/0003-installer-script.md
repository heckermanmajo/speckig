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
