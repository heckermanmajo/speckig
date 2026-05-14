# 009 — TypeScript / Angular Spec-Parser + UI-Integration

Goal: `// @spec ... // @end-spec`-Bloecke in TypeScript-Quellcode (Endung `.ts`) werden vom Speckig-Parser extrahiert. Angular-spezifische Patterns (`@Component`, `@Injectable`, `@Directive`, Klassen-Member mit Decorators) werden korrekt erkannt — Spec-Block direkt vor `@Decorator + class` gehoert zur Klasse, Decorator-Args (Selector etc.) werden als Strukturinfo mit aufgenommen. Fixture-getestet, Spec-View im Frontend.

Status: planned

## Tickets
- [ ] open/0001-ts-architecture-and-decorators.md
- [ ] open/0002-ts-parser.md
- [ ] open/0003-ts-fixture-tests.md
- [ ] open/0004-ts-ui-dispatch.md

## Out of scope
- `.tsx` (React/JSX) — eigenes Folge-Ticket, falls noetig. V1 ist `.ts` only.
- Angular-Template-HTML (`*.component.html`) — separate Doku, kein Spec-Parser-Fall.
- Type-Checking, Type-Inference, AST-Resolution. Wir parsen Spec + Signatur, nicht semantische Typen.
- TS-Compiler-API als Backend (Node-Subprozess) — V1 PHP-seitiger Tokenizer, Vendoring-Decision spaeter wenn noetig.
- Andere Sprachen (Nim, Lua, Groovy/Spring) — eigene Milestones.

See: pm/decisions/0006-spec-parser.md, pm/milestones/005-spec-parser/, pm/ideas/spec-as-comment.md
