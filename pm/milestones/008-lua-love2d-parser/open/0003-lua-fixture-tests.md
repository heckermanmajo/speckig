# 0003 — Lua-Fixture-Tests

Blocked by: 0002

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/lua/` mit Fixture-Paaren `<name>.lua` + `<name>.expected.json`.
- Mindestens diese Fixtures:
  - `love2d_callbacks.lua` — `love.load`, `love.update`, `love.draw`, `love.keypressed` mit Specs.
  - `local_function_with_spec.lua`.
  - `table_with_field_specs.lua` — `local config = { a = 1, b = 2 }` mit Feld-Specs ueber `a` und `b`.
  - `dangling_spec.lua`.
  - `spec_in_long_string.lua` — `[[ -- @spec ... ]]` als Long-String -> NICHT als Spec geparst.
  - `spec_in_block_comment.lua` — `--[[ -- @spec ... ]]` -> NICHT geparst.
  - `local_spec.lua` — Spec innerhalb einer Funktion -> `members[]` mit `kind: "local"`.
  - `no_spec.lua`.
- Test-Runner aus M005/0004 (oder neu falls noch nicht da) integriert Lua-Fixtures. Aufruf zeigt `lua: X/Y passed`.
- `php -l` sauber.

## Out of scope

- Andere Sprach-Fixtures.
- UI-Integration — 0004.

## Done

(append after work)
