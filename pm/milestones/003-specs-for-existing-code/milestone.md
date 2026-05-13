# 003 — Specs für bestehenden Code

Goal: Jede PHP-Datei unter `app/` (ausser vendored) bekommt eine `.spec`-Datei daneben — YAML, flach pro Funktion, mit `does` und wichtigen `conditions`. Liefert den ersten echten Spec-Layer und macht den Editor in M004 sinnvoll.

Status: active

## Tickets
- [x] archive/0001-spec-format-doku.md
- [x] archive/0002-share-core-specs.md
- [x] archive/0003-share-exceptions-specs.md
- [x] archive/0004-share-html-specs.md
- [x] archive/0005-user-data-specs.md
- [x] archive/0006-user-actions-specs.md
- [x] archive/0007-user-pages-specs.md
- [ ] open/0008-app-root-specs.md

## Out of scope
- Vendor-Code (`app/_share/vendor/`) bekommt keine Spec.
- Spec-Drift-Detection (Drift wird nur gezeigt, nicht erzwungen — siehe `Value.spec`).
- Spec-Parser im Speckig-UI (eigener späterer Milestone).
- Code-Änderungen während der Spec-Anlage (höchstens Trivial-Cleanups, kein Refactor).

See: pm/decisions/0005-spec-format.md
