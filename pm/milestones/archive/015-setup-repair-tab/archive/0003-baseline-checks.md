# 0003 — Baseline-Checks

## Goal
Der Setup-Tab zeigt einen sinnvollen Satz von Grund-Checks, die das
gesundheitlich Wichtige abdecken: PHP-Version, SPECKIG_ROOT, Repo-Pfad,
DB-Datei am kanonischen Ort, Vorhandensein/Aktualitaet der
`pm/how-to/`-Files.

## Notes
- "Auf dem aktuellen Stand" laesst sich nur gegen eine Referenz
  pruefen — die Referenz kommt aus 0005 (Baseline-Bundle). Bis das da
  ist, prueft der how-to-Check nur **Existenz**, nicht Inhalt. Ticket
  0005 zieht den Hash-Vergleich nach.
- PHP-Version min. 8.5 (siehe CLAUDE.md). Ergebnis bei 8.4: `fail` mit
  klarem Hint, kein `warn`.
- SPECKIG_ROOT-Check muss auch ohne gesetzte Env-Var sauber laufen
  (status `warn`/`fail` je nach Schwere).
- DB-Datei am kanonischen Ort: `app.sqlite` im Repo-Root (siehe
  Memory). Mehrere `app.sqlite*` ausserhalb sind ein `fail`.
- Vendor-Files (PHP unter `app/_share/vendor/`, JS unter
  `app/_share/js/` bzw. Vendor-Bereiche) muessen existieren — Check
  listet die erwarteten Pfade.

## Done when
- Setup-Tab zeigt mindestens diese Checks (Name + Status):
  PHP-Version, SPECKIG_ROOT, Repo-Pfad, DB-File-Lokation, how-to-Files
  vorhanden, Vendor-Files vorhanden.
- Auf einem gesunden Setup sind alle `ok`.
- Wenn man SPECKIG_ROOT testweise leert / `app.sqlite` versetzt,
  kippen die entsprechenden Checks auf `warn`/`fail` mit verstaendlichem
  Hint.
- Kein Check setzt automatisch `can_repair: true` — Reparatur-Wiring
  ist 0004.

## Out of scope
- Repair-Endpoints (0004).
- Hash-Vergleich mit Baseline (0005).
- Deployment-spezifische Checks (kommt in M016/0004).

See: pm/how-to/code_style.md

## Plan
- **In `setup_checks.php`** (aus 0002) folgende Checks hinzufuegen:
  - `check_php_version()` (aus 0002, ggf. Hint praeziser machen).
  - `check_speckig_root()` (aus 0002).
  - `check_repo_path()`: `realpath(__DIR__ . "/../..")` → wenn
    Verzeichnis existiert und enthaelt `app/` und `pm/` und
    `CLAUDE.md` → ok, sonst fail. Hint nennt den gefundenen Pfad.
  - `check_db_file()`: existiert `<repo>/app.sqlite`? `find` nach
    weiteren `app.sqlite*` ausserhalb Repo-Root macht hier kein Sinn
    (Server kann nicht ueberall sehen) — innerhalb des Repos pruefen:
    nur **eine** Datei mit Glob `app.sqlite*` direkt im Repo-Root, und
    keine in Unterordnern.
    - ok: genau `<repo>/app.sqlite` existiert.
    - warn: zusaetzliche `app.sqlite*` in Unterordnern.
    - fail: Datei fehlt komplett.
  - `check_howto_files()`: erwartete Liste der `pm/how-to/*.md`
    erstmal hart kodiert (`bugs.md, code_style.md, commit.md,
    decisions.md, ...` — Liste aus `ls pm/how-to/`). Pro Datei
    Existenz pruefen; alle vorhanden → ok, sonst fail mit Namen der
    fehlenden Files. Hash-Vergleich kommt in 0005, nicht hier.
  - `check_vendor_files()`: erwartete Vendor-Pfade:
    `app/_share/vendor/Parsedown.php`, `app/_share/vendor/js/codemirror.min.js`,
    `app/_share/vendor/css/codemirror.min.css`, plus die 7
    Mode-Files aus M013/0002 (`mode/markdown/markdown.js`,
    `mode/php/php.js`, etc.). Alle vorhanden → ok, sonst fail mit
    Liste fehlender Files.
- **Status-Schwellen pruefen**: PHP-Version 8.4 → `fail` (nicht warn),
  SPECKIG_ROOT leer → `warn`, DB-Datei fehlt → `fail`.
- **Keine Repair-Buttons**: alle `can_repair: false` in diesem Ticket
  — Repair-Wiring kommt in 0004, Baseline-Bundle erst in 0005.
- **Spec-Blocks** an jedem Check ergaenzen mit Vertrag und
  Status-Schwelle.
- **Files touched**: `app/_share/setup_checks.php`.

## Verifikation
- `php -l app/_share/setup_checks.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf setup.php: alle 6 Checks gruen.
- Manueller Failure-Test SPECKIG_ROOT: Server ohne env-Variable
  starten (`unset SPECKIG_ROOT; php -S ...`). Setup-Tab zeigt
  SPECKIG_ROOT als `warn`. Andere Checks bleiben gruen.
- Manueller Failure-Test DB-Datei: `mv app.sqlite /tmp/app.sqlite.bak`;
  Setup-Tab zeigt DB-Check `fail`. Restore: `mv /tmp/app.sqlite.bak
  app.sqlite`.
- Manueller Failure-Test how-to: `mv pm/how-to/bugs.md
  /tmp/bugs.md.bak`; Setup-Tab zeigt how-to-Check `fail` mit Namen.
  Restore: `mv /tmp/bugs.md.bak pm/how-to/bugs.md`.
- `git status` clean nach jeder Restore-Sequenz.

## Out of scope (Plan)
- Repair-Endpoints (0004).
- Hash-Vergleich gegen Baseline (0005).
- Deployment-Checks (M016/0004).

## Done
- `setup_checks::CHECKS` von 2 auf 6 Eintraege erweitert (Reihenfolge ==
  UI-Reihenfolge): `php_version`, `speckig_root`, `repo_path`, `db_file`,
  `howto_files`, `vendor_files`. Slugs sind stabile snake_case-Ids,
  `name` ist UI-Label.
- `check_php_version()` aus 0002 behalten, Hint praeziser: bei zu alter
  Version `"PHP 8.5+ erforderlich, gefunden: <version>"` (Schwelle
  `fail` bleibt, kein warn).
- `check_speckig_root()` aus 0002 unveraendert — bewusst `warn` (nicht
  `fail`) wenn ungesetzt, weil die Setup-View auch ohne env laeuft.
- `check_repo_path()` neu: `realpath(__DIR__ . "/../..")` aufloesen,
  Marker `app/`, `pm/`, `CLAUDE.md` per `file_exists` pruefen. Hint
  nennt den Pfad und bei `fail` die fehlenden Marker.
- `check_db_file()` neu: kanonisch `<repo>/app.sqlite` via `is_file`.
  Streu-Scan rekursiv via `RecursiveDirectoryIterator` + Callback-
  Filter (`.git/` ausgeschlossen) auf alle Files mit Praefix
  `app.sqlite`. Schwellen: existiert + keine Streu → `ok`, existiert
  + Streu → `warn` mit relativen Streu-Pfaden, fehlt → `fail`.
- `check_howto_files()` neu: 16 Files hart kodiert in
  `setup_checks::HOWTO_FILES` (Stand `ls pm/how-to/`). Pro Eintrag
  `file_exists` unter `<repo>/pm/how-to/<name>`. Alle vorhanden →
  `ok`, fehlt was → `fail` mit Liste fehlender Namen. Hash-Vergleich
  bleibt fuer 0005.
- `check_vendor_files()` neu: 12 Pfade hart kodiert in
  `setup_checks::VENDOR_FILES`. **Abweichung vom Plan**:
  - CSS-Pfad ist tatsaechlich `app/_share/vendor/css/codemirror.css`
    (nicht `codemirror.min.css` wie im Plan-Text). Realer Stand laut
    M013/0001 verbindlich.
  - Mode-Files liegen unter
    `app/_share/vendor/js/codemirror-modes/<name>.js` (nicht
    `js/mode/<name>/<name>.js`). Realer Stand laut M013/0002
    verbindlich.
  - 8 Modes statt 7 — `htmlmixed.js` ist Dependency von `php.js`,
    M013/0002 hat ihn mit vendored.
  - Spec-Block am `VENDOR_FILES`-Array dokumentiert die Abweichung
    und verweist auf M013/0001 + 0002 als Wahrheits-Quelle.
- Alle 6 Checks setzen `can_repair: false, repair_action: ""` —
  Repair-Wiring kommt in 0004.
- Spec-Blocks an jeder neuen Check-Methode und an
  `find_stray_sqlite_files()` mit Vertrag + Status-Schwellen.
- File-Header-Spec um den Baseline-Kontext (0003) und Verweis auf
  Wiring (0004) / Hash-Baseline (0005) ergaenzt.

Files touched:
- `app/_share/setup_checks.php` (4 neue Checks, 2 neue Konstanten,
  1 Helper, Spec-Blocks).
- `pm/milestones/015-setup-repair-tab/milestone.md` (Haekchen +
  archive/-Pfad).
- Ticket selbst nach `archive/`.

Smoketest-Belege (Server `SPECKIG_ROOT="$(pwd)" php -S 127.0.0.1:8086 -t app`):
- `php -l app/_share/setup_checks.php` → clean.
- `curl -s "http://127.0.0.1:8086/setup.php"` → HTTP 200, alle 6
  Check-Zeilen im HTML sichtbar.
- `curl -s "http://127.0.0.1:8086/setup.php" | grep -oE 'status-(ok|warn|fail)' | sort | uniq -c`
  → `6 status-ok` (alle Baseline-Checks gruen auf der Dev-Maschine:
  PHP 8.5.6, SPECKIG_ROOT gesetzt, app/pm/CLAUDE.md vorhanden,
  kanonisches app.sqlite, 16/16 how-to-Files, 12/12 Vendor-Files).
- Streu-File-Check: `find . -name "app.sqlite*" -not -path "./.git/*"`
  zeigt nur `./app.sqlite`. `find . -name "*.tmp.*" -not -path
  "./.git/*"` leer.
- Failure-Simulationen (DB versetzen, how-to umbenennen) bewusst
  weggelassen — User testet das manuell im Browser.
