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

(append after work)
