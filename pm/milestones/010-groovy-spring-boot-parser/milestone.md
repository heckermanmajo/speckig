# 010 — Groovy / Spring Boot Spec-Parser + UI-Integration

Goal: `// @spec ... // @end-spec`-Bloecke in Groovy-Quellcode (Endung `.groovy`) werden vom Speckig-Parser extrahiert. Spring-Boot-spezifische Patterns (`@RestController`, `@Service`, `@Repository`, `@Autowired`, `@RequestMapping`, `@GetMapping`, ...) werden erkannt — analog zur Decorator-Behandlung in TS/Angular werden Annotation-Args als Source-String mitgefuehrt. Fixture-getestet, Spec-View im Frontend.

Status: planned

## Tickets
- [x] archive/0001-groovy-architecture-and-annotations.md
- [ ] open/0002-groovy-parser.md
- [ ] open/0003-groovy-fixture-tests.md
- [ ] open/0004-groovy-ui-dispatch.md

## Out of scope
- Java-Quellcode (`.java`) — eigener Folge-Milestone, falls noetig. V1 ist `.groovy` only.
- Gradle-/Maven-Build-Files (`build.gradle`, `pom.xml`).
- Spring-AOP-Aspekte, Bean-Lifecycle-Resolution.
- Groovy-Closures-Semantik (we tokenize them; we don't analyze).
- Andere Sprachen (Nim, Lua, TS) — eigene Milestones.

See: pm/decisions/0006-spec-parser.md, pm/milestones/005-spec-parser/, pm/milestones/009-typescript-angular-parser/, pm/ideas/spec-as-comment.md
