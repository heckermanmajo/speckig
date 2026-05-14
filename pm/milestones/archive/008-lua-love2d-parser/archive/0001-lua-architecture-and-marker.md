# 0001 — Lua-Marker-Konvention + Architektur-Festlegung

Blocks: 0002, 0003, 0004

## Done when

- Marker-Konvention fuer Lua festgelegt und in `app/_share/spec_parser/README.md` dokumentiert: `-- @spec` ... `-- @end-spec`. Lua nutzt `--` als Zeilenkommentar, `--[[ ... ]]` als Block-Kommentar (NICHT als Spec-Marker). Begruendung im README.
- Granularitaet definiert: Datei-Header, `function` (Top-Level + lokal), Tabellen-Definitionen mit Methoden/Feldern, `local var`/`local function`. Love2D-spezifisch: `function love.load() ... end` etc. werden als Top-Level-Symbol mit qualifiziertem Namen erkannt (`name: "love.load"`).
- "Direkt darauffolgend"-Regel: zwischen Spec-Block und Symbol erlaubt sind Whitespace und nicht-Spec-Kommentare.
- Stub `app/_share/spec_parser/lua_parser.php` mit `lua_parser::parse()` (leeres Schema).
- Sprach-Dispatch in `spec_parser.php`: `.lua` -> `lua_parser::parse()`. CLI-Smoketest gegen Mini-`.lua` liefert valides JSON.
- README ergaenzt: Lua-Sektion mit Marker, Granularitaet, Love2D-Beispiel-Snippet (handgeschriebener Ziel-Output fuer 0002 mit `love.load` + `love.update` + `love.draw`).
- Parser-Strategie: PHP-seitiger State-Machine-Tokenizer, kein Regex (Decision 0006), keine externen Libs.
- `@spec`-Bloecke an neuen PHP-Dateien.
- `php -l` sauber.

## Out of scope

- Echter Parser — 0002.
- Fixture-Tests — 0003.
- UI / file.php — 0004.

## Done

- **Marker-Konvention** in `app/_share/spec_parser/README.md` (neue
  Sektion `## Lua`): `-- @spec` ... `-- @end-spec` als Lua-Zeilen-
  Kommentar. Begruendung im README: Lua hat nur `--` als Zeilen-
  Kommentar-Marker (kein Doc-Comment-Aequivalent zu Nims `##`), die
  Konvention schliesst visuell an die PHP/JS-Konvention `// @spec` an.
  Beispiel-Snippet (`function love.update(dt)` mit Spec) im README.
- **Granularitaet** im README dokumentiert: Datei-Header,
  `function name(...)` (Top-Level), qualifizierte
  `function table.method(...)` (Love2D-Pattern wie `function love.load`),
  `local function`, `local var` / `local table = { ... }`,
  Tabellen-Felder mit Specs in einem Tabellen-Literal, lokale
  Spec-Bloecke innerhalb eines Funktions-Body.
- **`kind`-Werte fuer Lua**: `function`, `method` (qualified
  `function table.method` wie `love.load`), `local-function`, `table`,
  `field`, `local-var`, `local`. Tabelle im README. Lua bekommt seine
  eigenen `kind`-Strings — qualifiziert vs. unqualifiziert wird durch
  `function`/`method` unterschieden, Skalar- vs. Tabellen-Local durch
  `local-var`/`table`.
- **"Direkt darauffolgend"-Regel** fuer Lua definiert: Whitespace,
  normale `--`-Zeilen-Kommentare (NICHT Spec) und Block-Kommentare
  `--[[ ... ]]` / `--[=[ ... ]=]` duerfen zwischen Spec-Block und Symbol
  stehen. Dangling-Verhalten (`"dangling spec at line N"`) wie bei den
  anderen Sprachen.
- **Parser-Strategie** im README festgelegt: PHP-seitiger State-Machine-
  Tokenizer (analog M005/0001 fuer JS, M007/0001 fuer Nim), kein Regex
  (Decision 0006), keine externen Libs, kein Subprozess. Nicht
  semantisch verstanden in V1: Metatables / `setmetatable`-Vererbung,
  innere Closures unterhalb der Top-Level-Funktion.
- **Edge-Cases** im README aufgefuehrt: `-- @spec`-Text in einzeiligen
  Strings (`'...'`, `"..."`), Long-Strings (`[[ ... ]]`,
  `[=[ ... ]=]` mit beliebig vielen `=`, matched levels) und
  Block-Kommentaren (`--[[ ... ]]`, `--[=[ ... ]=]`) wird NICHT als
  Marker erkannt. `--[[`-Block-Kommentar muss vom Tokenizer korrekt
  vom Long-String `[[...]]` unterschieden werden (Praefix `--` macht
  den Unterschied).
- **Beispiel-Snippet** (handgeschriebener Ziel-Output fuer M008/0002)
  im README: Love2D-Hauptdatei mit Datei-Spec, `function love.load()`
  mit Spec, `function love.update(dt)` mit Spec inkl. Conditions,
  `function love.draw()` mit Spec. JSON-Output inline, gleichem Schema
  wie die PHP/JS/Nim-Beispiele — alle drei Love2D-Callbacks erscheinen
  als `kind: "method"` mit qualifiziertem Namen `love.load` /
  `love.update` / `love.draw`.
- **Sprach-Dispatch-Tabelle** im README erweitert: Zeile fuer `.lua`
  -> `lua_parser::parse()` (Hinweis "PHP-seitiger Tokenizer
  (M008/0002)").
- **Stub** `app/_share/spec_parser/lua_parser.php` angelegt:
  `lua_parser::parse(string $path): array` liefert
  `["file_spec"=>[], "symbols"=>[], "warnings"=>[]]` fuer existierende
  Dateien, `["warnings" => ["file not found: ..."]]` sonst. Klassen-
  Name `lua_parser` lowercase nach Decision 0003. Namespace
  `_share\spec_parser`. Header-Spec (Datei + Klasse + parse-Methode)
  konsistent mit `nim_parser.php`.
- **Dispatcher** `app/_share/spec_parser/spec_parser.php` erweitert:
  `require_once .../lua_parser.php` ergaenzt; `if ($extension === "lua")`-
  Block analog zu `.nim`-Block; Doc-Spec der `parse()`-Methode erwaehnt
  jetzt auch `.lua`. Dispatcher-Form bleibt if-Kette (M005-Stil, kein
  Map-Lookup).
- **Files in diesem Verzeichnis**-Tabelle im README um `lua_parser.php`-
  Zeile erweitert.

### Verifikations-Belege

- `php -l app/_share/spec_parser/lua_parser.php` -> "No syntax errors detected".
- `php -l app/_share/spec_parser/spec_parser.php` -> "No syntax errors detected".
- `php app/_share/spec_parser/index.php /tmp/lua_smoke.lua` -> exit 0:

  ```
  {
      "file": "/tmp/lua_smoke.lua",
      "language": "lua",
      "file_spec": [],
      "symbols": [],
      "warnings": []
  }
  ```

- `php app/_share/spec_parser/tests/run.php` -> `19/19 passed`, exit 0
  (bestehende PHP/JS/Nim-Fixtures + synthetische Tests bleiben gruen,
  keine Regression).
- `grep -n "## Lua" app/_share/spec_parser/README.md` -> `330:## Lua`.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Temp-File `/tmp/lua_smoke.lua` nach Smoketest aufgeraeumt.
- Out-of-scope eingehalten: kein echter Lua-Parser implementiert
  (M008/0002), keine Fixtures (M008/0003), kein `app/file.php`-Touch
  (M008/0004), keine andere Sprache angefasst, README ausserhalb der
  neuen `## Lua`-Sektion und der Dispatch-/Files-Tabellen unveraendert.
