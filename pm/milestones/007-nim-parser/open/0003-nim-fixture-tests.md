# 0003 — Nim-Fixture-Tests

Blocked by: 0002

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/nim/` mit Fixture-Paaren `<name>.nim` + `<name>.expected.json`.
- Mindestens diese Fixtures:
  - `proc_with_conditions.nim` — Datei-Header + proc mit Spec inkl. mehreren Conditions.
  - `type_object_with_fields.nim` — `type T = object` mit Felder-Specs.
  - `dangling_spec.nim` — Spec-Block ohne folgendes Symbol -> Warning.
  - `spec_in_string.nim` — Triple-String und Raw-String mit `## @spec` als Text -> NICHT als Spec geparst.
  - `block_comment.nim` — `#[ ## @spec ... ]#` Block-Kommentar mit Spec-Text -> NICHT geparst.
  - `local_spec.nim` — Spec innerhalb eines proc-Body -> `members[]` mit `kind: "local"`.
  - `no_spec.nim` — kein `@spec` irgendwo -> leeres Schema.
- Test-Runner: `app/_share/spec_parser/tests/run.php` (falls schon aus M005/0004 da: erweitern; sonst neu) iteriert ueber alle Sprach-Fixture-Verzeichnisse und vergleicht JSON-Output mit `.expected.json`. Aufruf `php app/_share/spec_parser/tests/run.php` exit 0 bei allen-gruen, exit 1 sonst.
- Output-Zusammenfassung: `nim: X/Y passed`.
- `php -l` sauber.

## Out of scope

- UI-Integration — 0004.
- Andere Sprach-Fixtures (PHP/JS) — die leben in M005/0004.

## Done

(append after work)
