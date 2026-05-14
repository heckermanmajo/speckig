# 007 — Nim Spec-Parser + UI-Integration

Goal: `// @spec ... // @end-spec`-Bloecke in Nim-Quellcode (Endung `.nim`) werden vom Speckig-Parser extrahiert, gegen das Schema aus M005/0001 gelegt, fixture-getestet und im Frontend ueber `file.php` als Spec-View angezeigt. Sprach-Dispatch in `app/_share/spec_parser/spec_parser.php` wird um Nim erweitert.

Status: planned

## Tickets
- [ ] open/0001-nim-architecture-and-marker.md
- [ ] open/0002-nim-parser.md
- [ ] open/0003-nim-fixture-tests.md
- [ ] open/0004-nim-ui-dispatch.md

## Out of scope
- Andere Sprachen (Lua/Love2D, TS/Angular, Groovy/Spring) — eigene Milestones M008-M010.
- Migration bestehenden Nim-Codes auf `@spec`-Kommentare — eigener Migrations-Milestone falls Nim-Code im Repo landet.
- Nim-Compiler-API als Parser-Backend (Native Nim-Kompiler-Subprozess) — V1 ist PHP-seitiger Tokenizer, Vendoring/Subprocess waere spaetere Decision.

See: pm/decisions/0006-spec-parser.md, pm/milestones/005-spec-parser/, pm/ideas/spec-as-comment.md
