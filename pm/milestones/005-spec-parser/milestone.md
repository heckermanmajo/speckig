# 005 — Spec-Parser (PHP + JS) + UI-Integration

Goal: Spec-Bloecke (`// @spec ... // @end-spec`) in PHP- und JS-Dateien werden von einem echten Parser pro Sprache (kein Regex, siehe Decision 0006) extrahiert, gemeinsam mit Signatur/Typ aus dem Code in ein einheitliches JSON-Schema gegossen, fixture-getestet, und im Speckig-Frontend (`file.php`-Renderkette) ueber dem Datei-Inhalt als Spec-View angezeigt. Loest die alte `.spec`-Datei-Anzeige fuer Dateien, die schon migriert sind.

Status: planned

## Tickets
- [x] archive/0001-parser-architecture-and-schema.md
- [ ] open/0002-php-parser.md
- [ ] open/0003-js-parser.md
- [ ] open/0004-parser-fixture-tests.md
- [ ] open/0005-ui-spec-view-in-file-render.md

## Out of scope
- Andere Sprachen (Python, TS, Java, ...) — kommen, wenn Bedarf entsteht.
- Migration der restlichen `.spec`-Dateien auf das Kommentar-Format — eigener Milestone (M006) nach Pilot- und Parser-Review.
- Spec-Drift-Detection (`Value.spec`: Drift wird gezeigt, nicht erzwungen — Anzeige reicht).
- Editor-Mode mit Live-Spec-Preview — eigener spaeterer Milestone.

See: pm/decisions/0006-spec-parser.md, pm/ideas/spec-as-comment.md, pm/milestones/004-spec-as-comment-pilot/
