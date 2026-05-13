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
