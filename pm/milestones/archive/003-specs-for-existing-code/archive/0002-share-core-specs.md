# 0002 — Specs für app/_share/ Kern-Files

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder dieser 5 Files:
  - `app/_share/Action.php.spec` ← Action.php (abstract, from_array/try_from_array/execute_as_request_…)
  - `app/_share/DataClass.php.spec`
  - `app/_share/app.php.spec` (escape, redirect, enforce_login, enforce_plattform_admin, get_current_user, is_mobile, is_debug, somebody_is_logged_in, perform_login, error_log, …)
  - `app/_share/db.php.spec` (get_by_id, select, select_one, delete, create_and_update_table, save, get_db)
  - `app/_share/init.php.spec` (autoloader, session+csrf bootstrap, JSON-body merge, admin-seed)
- YAML laut Decision 0005.
- Schreibstil: knapp, kein Roman.

## Done

- 5 Spec-Files angelegt, jeweils neben der Code-Datei in `app/_share/`:
  - `Action.spec`: 5 functions, 4 conditions — abstract action runner.
  - `DataClass.spec`: minimal (nur `file` + `purpose`, kein `functions:`-Block).
  - `app.spec`: 15 functions, 7 conditions — app-weite Helpers.
  - `db.spec`: 8 functions, 4 conditions — SQLite/PDO-Layer.
  - `init.spec`: kein `functions:`-Block, dafür Top-Level `conditions:`-Liste
    mit 6 Bootstrap-Invarianten (Autoloader, Session+CSRF, JSON-Merge,
    Admin-Seed). Datei hat keine Klassen/Methoden.
- Filename-Konvention: `Foo.spec` (PHP-Endung *nicht* im Spec-Namen, gemäss
  Decision 0005 und `spec.md`). Das Ticket-Bullet schrieb `*.php.spec`, das
  ist mit der Decision unverträglich — `.spec` gewinnt.
- YAML-Validierung: alle 5 mit `python3 -c "import yaml; yaml.safe_load(...)"`
  sauber durch.
- Funktionszähl-Spotcheck: app.php 15 ↔ app.spec 15, db.php 8 ↔ db.spec 8,
  Action.php 5 ↔ Action.spec 5.
- Beobachtung: `app::log`, `app::error_log`, `app::function_log` sind
  aktuell no-op Stubs — Spec sagt "No-op placeholder" für jede.
  `app::is_debug` ist hardcoded `true` — Spec macht das sichtbar.
  Keine Funktion hat sich gegen einen 1-Satz-`does:` gewehrt.
- `init.spec` ohne `functions:`-Block strukturiert (kein Klassen-Wrapper),
  Top-Level `conditions:` für Bootstrap-Invarianten — flach im YAML,
  konsistent mit dem Geist von Decision 0005.
- Streu-File-Check: nur `/home/mo/Schreibtisch/speckig/app.sqlite`
  (kanonisch). Kein PHP-Code editiert.
