# 0002 — Lua-Parser (PHP-seitiger Tokenizer)

Blocked by: 0001

## Done when

- `app/_share/spec_parser/lua_parser.php` implementiert `lua_parser::parse()` nach Schema aus M005/0001.
- PHP-seitiger State-Machine-Tokenizer. Strings (single, double), Long-Strings (`[[...]]`, `[=[...]=]`), Block-Kommentare (`--[[ ... ]]`, `--[=[ ... ]=]`) duerfen nie als Spec missverstanden werden.
- Spec-Erkennung: `-- @spec` ... `-- @end-spec`, dazwischenliegende `--`-Zeilen-Kommentare als Spec-Zeilen.
- Symbol-Erkennung:
  - Top-Level `function name(...)` und `function table.method(...)` (Love2D-Pattern: `function love.load()`).
  - `local function name(...)`.
  - `local name = ...` Top-Level (Konstanten, Tabellen-Definitionen).
  - Tabellen-Literale mit Spec-bedeckten Feldern: `local foo = { -- @spec ... -- @end-spec\n  bar = 1, ... }`.
  - Lokale Spec-Bloecke innerhalb einer Funktion -> `members[]` mit `kind: "local"`.
- `kind`-Werte: `function`, `local-function`, `table`, `field`, `local`, `local-var`. Festlegung im README aus 0001.
- Signatur als Source-String (incl. Parameter, Default-Args gibt es in Lua nicht klassisch, aber `...` (vararg)).
- Existence-Check, Dangling-Spec-Warning wie bei den anderen Parsern.
- `php -l` sauber.

## Smoketest

- Mini-`.lua`-Datei mit Datei-Header-Spec, `function love.load()` mit Spec, `function love.update(dt)` mit Spec inkl. Conditions. Manuell parsen, Output-Snippet im Done.

## Out of scope

- Lua-Metatables-Analyse, Method-Vererbung.
- Fixture-Test-Runner — 0003.

## Done

- **Tokenizer** (`lua_parser::tokenize`) als State-Machine, kein Regex.
  Token-Klassen: `whitespace`, `newline`, `comment_line`, `comment_block`
  (matched-level `--[[ ... ]]` / `--[=[ ... ]=]`), `string_single`,
  `string_double` (jeweils mit `\`-Escape), `string_long` (matched-level
  `[[ ... ]]` / `[=[ ... ]=]`, KEINE Escapes), `number`, `identifier`,
  `punctuation`. Die `--[[` vs. `[[`-Unterscheidung wird dadurch garantiert,
  dass der `-`-Pfad VOR dem `[`-Pfad steht: ein `--` startet immer einen
  Kommentar (mit Long-Bracket-Test direkt danach), ein `[` ohne
  vorangestelltes `--` startet einen Long-String. Long-Bracket-Helper
  `try_read_long_bracket_open`/`close` lesen `=`-Level und matchen es
  exakt beim Schliessen — `[==[ ... ]==]` schliesst nur bei zwei `=`.
- **Walker** (`walk_tokens`) auf Top-Level-`depth==0`-Basis (so wie der
  JS-Parser). Spec-Bloecke werden via `read_spec_block` eingelesen
  (Marker-Inline-Inhalt zaehlt als erste Zeile, wie bei PHP/JS/Nim).
  Erster Spec-Block VOR dem ersten `function`/`local` wird `file_spec`,
  alle weiteren werden als `pending_spec` an das naechste deklarations-
  tragende Symbol gebunden. Dangling-Specs landen als
  `"dangling spec at line N"` in `warnings`.
- **Symbol-Erkennung**:
  - `function name(...)` -> `kind: "function"`.
  - `function table.method(...)` / `function obj:method(...)` ->
    `kind: "method"` mit qualifiziertem `name` (`"love.load"`,
    `"obj:greet"`).
  - `local function name(...)` -> `kind: "local-function"`.
  - `local name = { ... }` -> `kind: "table"` mit `members[]` aus
    `name = value`-Feldern; Spec-Bloecke direkt vor einem Feld werden
    diesem zugeordnet.
  - `local name = <skalar>` -> `kind: "local-var"` mit Source-String der
    RHS bis zum Top-Level-Newline.
- **Body-skip via end-counting**: `skip_to_block_end` faehrt durch den
  Funktions-Body, zaehlt `function`/`if`/`do` als `end`-Opener und
  `repeat` als `until`-Opener. `while`/`for` werden absichtlich NICHT
  gezaehlt — sie werden vom darauffolgenden `do` gepaart, sonst wuerden
  zwei `end`s erwartet. `elseif`/`else`/`then`/`in` sind Mid-Keywords
  ohne Counter-Effekt. So springt der Walker robust auch ueber
  geschachtelte Schleifen, if-Ketten und repeat-until-Bloecke hinweg.
- **Vararg `...`** als Punctuation-Token (3-Char-Operator vor 2-Char) —
  landet automatisch in der Argument-Liste, weil `read_balanced` rohen
  Source-String der Klammern liefert.
- **Edge-Cases verifiziert**: `-- @spec` in `'...'`, `"..."`, `[[...]]`,
  `[=[...]=]`, `--[[ ... ]]`, `--[=[ ... ]=]` werden NICHT als Marker
  erkannt, weil der Tokenizer sie als `string_single`/`string_double`/
  `string_long`/`comment_block` klassifiziert und `is_spec_start_token`
  nur auf `comment_line` matcht.

### Verifikations-Belege

- `php -l app/_share/spec_parser/lua_parser.php` -> "No syntax errors detected".

- `php app/_share/spec_parser/index.php /tmp/lua_smoke.lua` -> exit 0:
  ```
  "file_spec": ["File-level: tiny demo for lua/love2d parser smoketest."],
  symbols:
    method love.load    spec=["Initialize game state on startup."]
                        members=[local "local: nothing yet, placeholde"]
    method love.update  spec=["Advance simulation by dt seconds.",
                              "throws if dt is negative"]
    method love.draw    spec=["Render current frame."]
  warnings: ["dangling spec at line 29"]
  ```

- `php app/_share/spec_parser/index.php /tmp/lua_strings.lua` -> exit 0,
  vier `local-var`-Eintraege (`s`, `d`, `long`, `lvl`), `warnings: []`,
  KEINE fake-Specs.

- `php app/_share/spec_parser/index.php /tmp/lua_block_comment.lua` ->
  exit 0, `function real_function`, `warnings: []`, keine fake-Specs.

- `php app/_share/spec_parser/index.php /tmp/lua_dangling.lua` -> exit 0,
  `function ok` plus `warnings: ["dangling spec at line 2"]`.

- Zusatz-Smoketest `/tmp/lua_extra.lua` (intern, danach geloescht):
  table mit Spec-Field, `local function`, `function obj:method`, vararg
  `function sum(...)`, `looper` mit verschachteltem for/if/while/repeat —
  alle korrekt erkannt, keine spurious dangling-Warnings.

- `php app/_share/spec_parser/tests/run.php` -> `19/19 passed` (PHP 6/6,
  JS 4/4, Nim 7/7), keine Regression.

- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).

- Temp-Files `/tmp/lua_smoke.lua`, `/tmp/lua_strings.lua`,
  `/tmp/lua_block_comment.lua`, `/tmp/lua_dangling.lua`,
  `/tmp/lua_extra.lua` nach Verifikation aufgeraeumt.

- Out-of-scope eingehalten: keine Lua-Fixtures im Test-Runner (das ist
  0003), kein Touch an `app/file.php` (0004), README unveraendert
  (Schema steht schon aus 0001), andere Sprach-Parser unangetastet.
