# 0003 — Specs für app/_share/exceptions/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder Exception-Klasse:
  - `ActionInputError.spec`
  - `BadCredentialsError.spec`
  - `BadStateError.spec`
  - `IdNotFoundError.spec`
  - `NeedsLoginError.spec`
  - `NotAllowedError.spec`
  - `SystemError.spec`
  - `UserError.spec`
  - `UserInputError.spec`
- Exceptions haben oft keine eigenen Funktionen — `functions: []` weglassen.
- `purpose` reicht meistens, plus ggf. eine `condition` à la "wird geworfen wenn …".
- Schema kann minimal sein: nur `file` und `purpose`.

## Done

- 9 Spec-Files in `app/_share/exceptions/`, eine pro Exception-Klasse:
  - `ActionInputError.spec`: thrown in 3 places (Action.php from_array TypeError/ArgumentCountError, bind_input_to_parameters missing, bind_input_to_parameters unknown field).
  - `BadCredentialsError.spec`: thrown in 1 place (LoginAction password_verify fail).
  - `BadStateError.spec`: thrown in 1 place (CreateUserAction db::save returned no id).
  - `IdNotFoundError.spec`: thrown in 2 places (app::get_current_user, LoginAction username not found).
  - `NeedsLoginError.spec`: thrown in 2 places (app::enforce_plattform_admin, app::get_current_user_id).
  - `NotAllowedError.spec`: thrown in 2 places (both in app::enforce_plattform_admin — session user missing and non-admin).
  - `SystemError.spec`: unused — purpose marks it as reserved, no `conditions:`.
  - `UserError.spec`: base class, never thrown directly — purpose lists subclasses, no `conditions:`.
  - `UserInputError.spec`: thrown in 8 places across LoginAction (2) and CreateUserAction (6) — collapsed to two summary conditions to keep the spec terse.
- YAML-Check (`python3 -c "yaml.safe_load(...)"`) für alle 9 grün.
- Schema flach: nur `file`, `purpose`, optional Top-Level `conditions:` — kein `functions:`-Block (Exceptions haben keine eigenen Methoden, erben alles).
- Sprache Englisch, konsistent mit M003/0002.
- Spotcheck: grep `throw new BadCredentialsError` → 1 Treffer in LoginAction.php:44 (Spec-Aussage stimmt). grep `throw new IdNotFoundError` → 2 Treffer (app.php:103, LoginAction.php:36) — beide in Spec abgedeckt.
- Beobachtungen:
  - `SystemError` ist im Repo nirgends benutzt — extends Exception (nicht UserError). Spec sagt das ehrlich.
  - `UserError` selbst wird nie direkt geworfen — fungiert nur als Basisklasse für 6 der 8 anderen Exceptions (alle ausser `SystemError`).
  - Vererbungs-Hierarchie: 7 erben von `UserError`; `UserError` und `SystemError` erben direkt von `Exception`.
- Streu-File-Check: nur `/home/mo/Schreibtisch/speckig/app.sqlite` (kanonisch). Kein PHP-Code editiert.
