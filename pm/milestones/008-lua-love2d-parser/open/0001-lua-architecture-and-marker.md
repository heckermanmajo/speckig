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

(append after work)
