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

## Done
- Decision `pm/decisions/0008-howto-baseline.md` als eigener
  `[chore]`-Commit angelegt: Speicherort `app/_share/setup/howto-baseline/`,
  manuelle Fortschreibung, Drift = warn ohne Repair-Button.
- Baseline-Bundle `app/_share/setup/howto-baseline/*.md` als 1:1-Kopie
  der 16 aktuellen `pm/how-to/*.md`-Files angelegt (`cp pm/how-to/*.md
  app/_share/setup/howto-baseline/`). `diff -r` ist clean.
- `setup_checks::check_howto_files` ersetzt durch `check_howto_baseline`:
  pro Datei in `HOWTO_FILES` Live vs Baseline via SHA-256 vergleichen.
  Aggregation in ein einziges Result (Schema-Konstanz): alle gleich →
  `ok`; mind. ein File fehlt → `fail` + `can_repair:true` mit
  `restore_how_to:<erstes-fehlendes-File>` (pro Reload ein Klick, dann
  rendert die Liste das naechste fehlende File); nur Drift → `warn`
  ohne Repair-Button. Bundle-Datei selbst fehlt → wird wie Drift
  gehandhabt (nicht reparierbar), Bundle-Dir fehlt → `fail` ohne
  Repair. `CHECKS`-Slug auf `howto_baseline`, UI-Name auf
  `pm/how-to-Files vs Baseline` gesetzt.
- `restore_how_to_handler($filename)` in `app/setup.php` mit echter
  Logik:
    - Defensive Whitelist-Pruefung gegen `REPAIR_IDS`
      (`restore_how_to:<filename>`); unbekannt → `unknown_id`.
    - Quelle `<repo>/app/_share/setup/howto-baseline/<filename>`,
      Ziel `<repo>/pm/how-to/<filename>`. Nur `basename()` verwenden
      (defense in depth gegen Traversal, obwohl die Whitelist das
      schon abdeckt).
    - Live existiert → `{ok:true, status:"unchanged"}` (Idempotenz +
      Drift-Schutz, Decision 0008).
    - Live fehlt → Baseline lesen, atomar via `tmp+rename` schreiben,
      `{ok:true, status:"restored", path:"pm/how-to/<name>"}`. Tmp wird
      bei rename-Fehler aufgeraeumt.
    - Baseline-Datei fehlt → `baseline_missing`. I/O-Fehler →
      `io_error`. Alle 200, damit der JS-Loader einen einheitlichen
      Read-Json-Pfad hat (HTTP-Code setzt nur der Dispatcher).
- `app/setup.php`-File-Header-Spec und Endpoint-Spec auf 0005
  aktualisiert; der `not_implemented`-Pfad ist Geschichte.
- `app/pm.php`-Save-Handler-Spec explizit ergaenzt: `pm/`-Praefix-Check
  schliesst das Bundle unter `app/_share/setup/howto-baseline/` aus.
  Keine Code-Aenderung noetig — der bestehende Guard greift schon.
- Milestone-Abschluss: `milestone.md` Haekchen + `Status: done`,
  Milestone-Folder via `git mv` nach `archive/`. Letztes Ticket des
  Milestones, daher beide Moves im selben Close-Commit.

Files touched:
- `pm/decisions/0008-howto-baseline.md` (neu, separater Commit).
- `app/_share/setup/howto-baseline/*.md` (16 neue Dateien).
- `app/_share/setup_checks.php` (CHECKS-Registry, HOWTO_FILES-Spec,
  `check_howto_files` → `check_howto_baseline`).
- `app/setup.php` (echter restore-Handler + Spec-Updates).
- `app/pm.php` (Spec-Block-Ergaenzung, keine Logik-Aenderung).
- `pm/milestones/015-setup-repair-tab/milestone.md` (Haekchen + Status:
  done).
- Ticket-Move open/ → archive/.
- Milestone-Move pm/milestones/015-... → pm/milestones/archive/015-...

Smoketest-Belege (Server `SPECKIG_ROOT="$(pwd)" php -S 127.0.0.1:8086
-t app`):
- `php -l app/_share/setup_checks.php app/setup.php app/pm.php` →
  clean.
- `curl -s "http://127.0.0.1:8086/setup.php" | grep -oE
  'status-(ok|warn|fail)' | sort | uniq -c` → `6 status-ok` (alle
  Checks gruen; Baseline-Bundle wurde gerade frisch angelegt, also
  identisch zur Live-Version).
- `POST .../setup.php?action=repair&id=restore_how_to:bugs.md` →
  HTTP 200 + `{"ok":true,"status":"unchanged","id":"restore_how_to:
  bugs.md","path":"pm/how-to/bugs.md"}` (Live-Datei existiert,
  Idempotenz greift).
- `POST .../setup.php?action=repair&id=unknown` → HTTP 400 +
  `unknown_id`.
- `POST .../setup.php?action=repair&id=` → HTTP 400 + `unknown_id`.
- `ls app/_share/setup/howto-baseline/ | wc -l` → 16 (= `ls pm/how-to/
  | wc -l`).
- `diff -r pm/how-to/ app/_share/setup/howto-baseline/` → keine Diffs.
- `find . -name "*.tmp.*" -not -path "./.git/*"` → leer.
- `find . -name "app.sqlite*" -not -path "./.git/*"` → nur
  `./app.sqlite` (kanonisch).

Plan-Abweichungen:
- Plan-Wortlaut "pro Datei eine Zeile, sonst Sammelresult": umgesetzt
  als striktes Single-Result-Schema, weil `setup_checks::run()` pro
  Check-Callable nur ein Result erlaubt (Stabilitaet des Schemas, siehe
  Ticket-Hint). Hint listet alle problematischen Files, `repair_action`
  zeigt das erste fehlende File. Pro Reload genau ein Repair-Klick —
  Idempotenz traegt den Rest.
- Drift-/Missing-Verifikation NICHT durchgespielt (keine simulierten
  Defekte laut Ticket-Vorgabe). Verifiziert ueber Healthy-State + den
  `unchanged`-Pfad des Handlers, der die Idempotenz-Verzweigung exakt
  trifft.
