# 008 — Lua (Love2D) Spec-Parser + UI-Integration

Goal: `-- @spec ... -- @end-spec`-Bloecke in Lua-Quellcode (Endung `.lua`) werden vom Speckig-Parser extrahiert, Love2D-spezifische Patterns (`love.load`, `love.update`, `love.draw`, ...) werden korrekt als top-level Funktionen erkannt, fixture-getestet und im Frontend angezeigt. Sprach-Dispatch in `app/_share/spec_parser/spec_parser.php` wird um Lua erweitert.

Status: done

## Tickets
- [x] archive/0001-lua-architecture-and-marker.md
- [x] archive/0002-lua-parser.md
- [x] archive/0003-lua-fixture-tests.md
- [x] archive/0004-lua-ui-dispatch.md

## Out of scope
- Andere Sprachen (Nim, TS/Angular, Groovy/Spring) — eigene Milestones.
- LuaJIT-spezifische Syntax (FFI-Cdecl-Strings) — V1 parst Standard-Lua.
- Love2D-Asset-Indexing (Sprites, Audio).
- Migration echten Lua-Codes — nur Test-Fixtures.

See: pm/decisions/0006-spec-parser.md, pm/milestones/005-spec-parser/, pm/ideas/spec-as-comment.md
