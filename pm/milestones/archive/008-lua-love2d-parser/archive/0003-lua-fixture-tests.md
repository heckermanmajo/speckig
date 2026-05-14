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

- **Verzeichnis** `app/_share/spec_parser/tests/fixtures/lua/` mit acht
  Fixture-Paaren `<name>.lua` + `<name>.expected.json` angelegt:
  - `love2d_callbacks.lua` — Datei-Spec + `function love.load()`,
    `function love.update(dt)`, `function love.draw()`,
    `function love.keypressed(key)` mit Specs. Erwartet vier
    `kind: "method"` mit qualifizierten Namen.
  - `local_function_with_spec.lua` — Datei-Spec + `local function calculate(a, b)`
    mit Spec. Erwartet ein `kind: "local-function"`.
  - `table_with_field_specs.lua` — `local config = { ... }` mit drei
    Spec-bedeckten Feldern (`width`, `height`, `frame_budget`).
    Parser liefert `kind: "table"` mit `members[]` von
    `kind: "field"`. Der erste Spec-Block laeuft als `file_spec`
    durch, weil er vor dem ersten Top-Level-Decl steht — die Tabelle
    selbst hat darum `spec: []`. Diskrepanz dokumentiert: das ist
    parser-konsistent (Datei-Header-Heuristik), nicht falsch.
  - `dangling_spec.lua` — eine valide `function ok()` + Spec-Block am
    Datei-Ende ohne folgendes Symbol. Erwartet `warnings: ["dangling spec at line 12"]`.
  - `spec_in_long_string.lua` — Top-Level `local long = [[ ... ]]` und
    `local lvl = [==[ ... ]==]` mit `-- @spec`-Markern im
    Long-String-Body. Erwartet zwei `kind: "local-var"`-Eintraege,
    KEINE fake-Specs, `warnings: []`. Der Parser legt die kompletten
    Long-Strings (inklusive der eingebetteten `-- @spec`-Zeilen und
    Newlines) als `default`-Source-String ab — das ist die korrekte
    Round-Trip-Form.
  - `spec_in_block_comment.lua` — `--[[ ... ]]` und `--[=[ ... ]=]`
    mit Spec-Text + `function real_function()`. Erwartet nur die
    Funktion, keine fake-Specs, `warnings: []`. Da KEIN `-- @spec`-
    Zeilen-Kommentar vor `real_function` steht, ist `file_spec: []`
    und `spec: []` der Funktion korrekt leer.
  - `local_spec.lua` — `function compute(a, b)` mit Body, in dem
    `-- @spec ... -- @end-spec` einen lokalen Code-Abschnitt
    beschreibt. Erwartet ein Member mit `kind: "local"` und
    Name-Hint `"local guard: refuse negative i"` (30-Char-Truncate
    durch `mb_substr` im Parser, identisch zum Nim-Verhalten).
  - `no_spec.lua` — `local count = 0` + `function bare_helper(n)`,
    keine `-- @spec`-Marker. Erwartet `file_spec: []`, beide
    Symbole mit `spec: []`, `warnings: []`.

- **Test-Runner** `app/_share/spec_parser/tests/run.php` analog zur
  Nim-Erweiterung in M007/0003 um Lua erweitert: Header-Spec
  ergaenzt, `lang_counts`-Spec ergaenzt, `run_fixture`-Spec ergaenzt,
  `run_all_fixtures` zieht zusaetzlich `lua/*.lua` per `glob`,
  Lua-Gruppe in `$groups` aufgenommen, `lua` an die Pro-Sprache-
  `$preferred_order`-Liste angehaengt. `php -l` sauber.

- **Diskrepanzen Schema vs. Parser**:
  1. Bei `table_with_field_specs.lua` wandert der erste Spec-Block
     in `file_spec` statt zur Tabelle, weil er vor dem ersten
     deklarations-tragenden Token steht — das ist die parser-weite
     Datei-Header-Heuristik (siehe `lua_parser::walk_tokens`,
     `first_decl_index`). Wenn man explizit eine Tabellen-Spec
     wollte, muesste ein zweiter Spec-Block direkt vor `local config`
     stehen. Verhalten dokumentiert, nicht als Bug klassifiziert.
  2. Tabellen-Felder werden mit zusaetzlichem `default`-Feld
     (Source-String der RHS) ausgegeben, nicht nur `name`+`spec` wie
     Nim-Object-Felder. Das passt zu `read_field_rhs` und ist im
     README-Schema (`default` als optionales Feld) gedeckt.
  3. `local-var` mit `default` (nicht-Tabelle) tragen das `default`-
     Feld ebenfalls. Forward-decls (`local name` ohne `=`) wuerden
     keins haben — keine Fixture deckt diesen Fall ab, ist aber
     ausserhalb der Ticket-Scope.

- Keine Parser-Bugs im Verlauf der Fixture-Erstellung gefunden.
  `lua_parser` aus M008/0002 bleibt unangefasst.

### Verifikations-Belege

- `php -l app/_share/spec_parser/tests/run.php` -> "No syntax errors detected".

- `php app/_share/spec_parser/tests/run.php` -> exit 0,
  `27/27 passed`, Pro-Sprache:
  ```
  php: 6/6
  js: 4/4
  nim: 7/7
  lua: 8/8
  ```
  Keine Regression an PHP/JS/Nim-Fixtures.

- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).

- Out-of-scope eingehalten: `app/file.php` nicht angefasst (das ist
  0004), `lua_parser.php` unveraendert, README unveraendert, andere
  Sprach-Fixtures unangetastet.
