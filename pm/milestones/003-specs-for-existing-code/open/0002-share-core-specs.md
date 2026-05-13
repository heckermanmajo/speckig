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
