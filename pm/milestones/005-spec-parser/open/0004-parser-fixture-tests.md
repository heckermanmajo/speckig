# 0004 — Parser-Fixture-Tests (PHP + JS)

See: pm/how-to/testing.md, pm/ideas/spec-as-comment.md
Blocked by: 0002, 0003

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/` enthaelt Fixture-Paare:
  - `php/<name>.php` + `php/<name>.expected.json`
  - `js/<name>.js` + `js/<name>.expected.json`
- Mindestens diese Fixtures sind drin (PHP):
  - `class_with_fields.php` — entspricht Pilot-`User.php`, sechs Felder, Datei- und Klassen-Spec.
  - `function_with_conditions.php` — entspricht Pilot-`CreateUserAction.php::execute`, Spec mit Intent + Conditions-Zeilen.
  - `dangling_spec.php` — Spec-Block ohne folgendes Symbol -> `warnings[]` enthaelt Eintrag.
  - `spec_in_string.php` — Heredoc und Strings, die `// @spec` als Text enthalten -> werden NICHT als Spec geparst.
  - `vendor_blacklist.php` — Datei unter `app/_share/vendor/` -> Parser lehnt ab.
  - `no_spec.php` — Datei ohne jede Spec -> `file_spec: []`, `symbols[]` ohne `spec`-Eintraege.
  - `local_spec.php` — Spec innerhalb einer Methode -> erscheint in `members[]` mit `kind: local`.
- Mindestens diese Fixtures sind drin (JS):
  - `function_with_spec.js`, `class_with_spec.js`, `regex_literal.js` (Regex-Literal mit `//` darf nicht als Spec interpretiert werden), `no_spec.js`.
- Test-Runner: ein simples `app/_share/spec_parser/tests/run.php`, das alle Fixtures durchgeht, Parser ruft, JSON-Output mit `.expected.json` per Deep-Compare vergleicht (kein Regex; `json_decode` + rekursiver Array-Vergleich) und am Ende eine Zusammenfassung ausgibt: `X/Y passed`.
- Aufruf `php app/_share/spec_parser/tests/run.php` exit 0 wenn alle gruen, exit 1 sonst.
- Smoketest im Ticket: Output mit Pass-Counter zeigen.

## Aus der Idea wichtig

- Fixtures sind die Wahrheits-Tests. Wenn eine Fixture fehlt, fehlt Test-Coverage — keine Unit-Tests als Ersatz.
- Bei Aenderungen am Schema (0001) muessen alle `.expected.json` mit aktualisiert werden — das ist explizit gewollt, weil es das Schema diszipliniert haelt.

## Done

(append after work)
