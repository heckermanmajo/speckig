# 0005 — How-to-Baseline als versioniertes Asset

## Goal
Es gibt einen versionierten "Referenz-Stand" der `pm/how-to/`-Files im
Repo, gegen den die Setup-Checks vergleichen koennen und aus dem
Repair-Aktionen fehlende How-tos wiederherstellen.

## Notes
- Naheliegender Ort: `app/_share/setup/howto-baseline/<name>.md` —
  separater Pfad, damit klar ist: das ist eine Snapshot-Kopie, kein
  Live-Doc.
- Wie der Stand **fortgeschrieben** wird, ist eine Decision wert
  (siehe Out-of-scope: Decision schreiben, nicht im Ticket
  improvisieren). Vorschlag fuer die Decision: Baseline wird per Hand
  aktualisiert, immer wenn eine how-to-Konvention sich aendert; ein
  Setup-Check meldet "Drift" als `warn` ohne automatischen Repair.
- Hash-Vergleich: SHA-256 ueber den Dateiinhalt. Setup-Check sagt
  `ok` bei Gleichheit, `warn` bei Drift, `fail` bei fehlender
  Live-Datei.
- Repair-Action `restore_how_to:<name>` kopiert das Baseline-File auf
  den Live-Pfad — **nur** bei fehlender Live-Datei. Bei Drift kein
  ueberschreiben (das ist Userin-Entscheidung).
- Baseline-Bundle nicht in die Save-/Edit-Whitelists aufnehmen — es
  ist ein Asset, kein editierbares Doc.

## Done when
- Verzeichnis `app/_share/setup/howto-baseline/` existiert und enthaelt
  Kopien jedes aktuellen `pm/how-to/*.md`.
- Setup-Tab zeigt pro how-to-File: vorhanden / Drift / fehlend.
- Repair-Action `restore_how_to:bugs.md` legt `pm/how-to/bugs.md`
  wieder an, wenn man es vorher testweise verschiebt.
- Drift-Fall (Live-Datei manuell veraendert) zeigt `warn` und bietet
  **keinen** Repair-Button.
- Eine Decision-Datei in `pm/decisions/` dokumentiert die
  Fortschreibungs-Regel der Baseline.

## Out of scope
- Auto-Sync zwischen Live und Baseline.
- Diff-Anzeige zwischen Live und Baseline (kann ein spaeteres Ticket
  werden).
- Baseline fuer andere Verzeichnisse als `pm/how-to/`.

See: pm/how-to/decisions.md

## Plan
- **Decision schreiben** (Phase vor Implementierung): neue Datei
  `pm/decisions/0008-howto-baseline.md` mit drei Stichpunkten:
  - Baseline-Bundle liegt unter `app/_share/setup/howto-baseline/`.
  - Fortgeschrieben wird das Bundle **per Hand**, wenn eine
    `pm/how-to/*.md`-Konvention sich aendert. Kein Auto-Sync.
  - Drift (Inhalt unterschiedlich zwischen Live und Baseline) ist
    `warn` ohne Repair-Button — die Aktualisierung ist Userin-Sache.
  Eigener `[chore]`-Commit vor der eigentlichen 015/0005-
  Implementierung, weil Decisions als separate Commits laufen
  (siehe `pm/how-to/process.md`).
- **Bundle anlegen**: Verzeichnis `app/_share/setup/howto-baseline/`,
  fuer jede aktuell vorhandene `pm/how-to/*.md` eine **Kopie** mit
  identischem Inhalt. Tooling: simple `cp pm/how-to/*.md
  app/_share/setup/howto-baseline/`.
- **Checks erweitern** (`app/_share/setup_checks.php`):
  - `check_howto_files()` aus 0003 ersetzt durch
    `check_howto_baseline()`:
    - Pro Datei in `app/_share/setup/howto-baseline/`:
      - Live-Datei fehlt → Eintrag `status: fail, can_repair: true,
        repair_action: "restore_how_to:<name>"`.
      - Live-Datei existiert, SHA-256 ungleich → `warn, can_repair:
        false`, hint nennt Drift.
      - Gleich → `ok`.
    - Alle ok → ein einziger Sammelresult `ok`. Sonst pro Datei eine
      Zeile.
- **Repair-Handler ausfuellen** (`setup.php`):
  - `restore_how_to_handler($filename)`:
    - Pruefen ob `$filename` in Whitelist (REPAIR_IDS aus 0004).
    - Quelle: `app/_share/setup/howto-baseline/<filename>`.
    - Ziel: `pm/how-to/<filename>`.
    - Nur schreiben wenn Ziel **nicht existiert** (Idempotenz +
      Drift-Schutz). Sonst `{ok:true, status:"unchanged"}`.
    - Atomar tmp+rename.
    - Antwort: `{ok:true, status:"restored"|"unchanged", path}`.
- **Save-Endpoint-Schutz**: Pfade unter
  `app/_share/setup/howto-baseline/` muessen vom Save-Endpoint
  abgelehnt werden — die Baseline ist ein Asset. M014/0001 baut die
  Whitelist auf `pm/...` ein, Baseline liegt unter `app/`, ist also
  schon ausserhalb. Keine zusaetzliche Aenderung noetig, aber im
  Spec-Block der Save-Handler explizit erwaehnen.
- **Files touched**:
  - `pm/decisions/0008-howto-baseline.md` (neu, eigener Commit).
  - `app/_share/setup/howto-baseline/*.md` (Bundle, im 015/0005-
    Close-Commit).
  - `app/_share/setup_checks.php` (Drift-Check).
  - `app/setup.php` (restore_how_to-Handler).

## Verifikation
- `php -l app/_share/setup_checks.php app/setup.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf setup.php: alle how-to-Files `ok`.
- Drift-Test: `echo "drift" >> pm/how-to/bugs.md`; Setup-Tab zeigt
  `warn` fuer bugs.md, kein Repair-Button. Restore: `git checkout --
  pm/how-to/bugs.md`.
- Missing-Test: `mv pm/how-to/bugs.md /tmp/bugs.md.bak`; Setup-Tab
  zeigt `fail` fuer bugs.md mit Repair-Button. Klick → Datei wieder
  da, Inhalt identisch zum Baseline (`diff
  app/_share/setup/howto-baseline/bugs.md pm/how-to/bugs.md` leer).
- Idempotenz: zweiter `restore`-Aufruf via curl → 200 +
  `status:"unchanged"`.
- Save-Endpoint: `curl -s -X POST --data x
  "http://127.0.0.1:8086/pm.php?action=save&path=app/_share/setup/howto-baseline/bugs.md"`
  → 400 (Pfad liegt nicht unter `pm/` — der bestehende Guard greift).
- `find . -name "*.tmp.*" -not -path "./.git/*"` leer.
- `git status` clean.

## Out of scope (Plan)
- Diff-Anzeige im UI.
- Auto-Sync.
- Baseline-Bundles fuer andere Verzeichnisse.
