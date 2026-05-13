# 0003 — Methoden in Action.php auf snake_case umbenennen

See: pm/decisions/0002-php-infra.md, pm/how-to/code_style.md

Decision: alle Methoden sind `snake_case`. Der einkopierte Code hat drei camelCase-Methoden in `app/_share/Action.php`, die jetzt umbenannt werden.

## Done when
- `Action::bindInputToParameters` → `Action::bind_input_to_parameters`
- `Action::tryFromArray` → `Action::try_from_array`
- `Action::getExecuteMethod` → `Action::get_execute_method`
- Aufrufer in `app/_share/Action.php` (interne Aufrufe) und `app/api.php` mitgezogen.
- `grep -rE "(bindInputToParameters|tryFromArray|getExecuteMethod)" app/` ist leer.
- `php -S 127.0.0.1:8080 -t app` startet weiterhin ohne Syntax-Fehler.

## Out of scope
- `from_array` bleibt — ist schon snake_case.
- `execute_as_request_and_dump_json_and_exit` bleibt — schon snake_case.
- Andere camelCase-Methoden ausserhalb `Action.php` (gibt es laut grep keine).

## Done
- `app/_share/Action.php`: Definitionen umbenannt — `bindInputToParameters` → `bind_input_to_parameters`, `tryFromArray` → `try_from_array`, `getExecuteMethod` → `get_execute_method`. Drei interne Aufrufer in `from_array` und `execute_as_request_and_dump_json_and_exit` mitgezogen.
- `app/api.php`: kein echter Call, nur eine Erwähnung im Erklär-Kommentar (`# the Action::bindInputToParameters logic ...`) — auf den neuen Namen aktualisiert, damit der Kommentar nicht ins Leere zeigt.
- Verifiziert: `grep -rE "(bindInputToParameters|tryFromArray|getExecuteMethod)" app/` leer; `php -l` auf beiden Files „No syntax errors"; `php -S 127.0.0.1:8080 -t app` plus `curl /` liefert HTTP 200.
- DB-PDOException beim Bootstrap weiterhin sichtbar (User-Tabelle fehlt) — out of scope, gehört zu 0004.
