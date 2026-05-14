# 0003 — JS-Parser

See: pm/ideas/spec-as-comment.md, pm/decisions/0006-spec-parser.md
Blocked by: 0001

## Done when

- `app/_share/spec_parser/js_parser.php` (oder `.js`, je nach Architektur-Entscheidung in 0001) implementiert die Schnittstelle aus 0001 fuer JS-Dateien.
- Parser nutzt einen echten JS-Tokenizer/AST — entweder eine vendored Lib (Acorn / Esprima unter `app/_share/vendor/js/`, falls JS ausgefuehrt wird) oder einen PHP-seitigen JS-Token-Walker. Entscheidung folgt aus 0001.
- **Kein Regex** — gilt auch hier.
- Parser erkennt:
  - Datei-Header-Spec.
  - `function`/`class`/`const`/`let`/`var` Top-Level-Deklarationen mit Spec-Block direkt davor.
  - Klassen-Methoden und -Felder mit Spec-Block direkt davor.
  - Lokale Spec-Bloecke innerhalb einer Funktion (`kind: local`).
- Parser ignoriert vendored Code: `app/_share/vendor/js/` ist Pfad-Blacklist.
- Smoketest: eine Mini-`fixture.js`-Datei mit Datei-Spec, einer Funktion + Spec, einer const + Spec wird korrekt gerendert.
- **Tests fuer dieses Ticket:** Fixture-Datei manuell parsen. Echte Fixture-Tests in 0004.

## Aus der Idea wichtig

- Strings (single, double, template literals, regex literals) duerfen nie als Spec-Marker missverstanden werden. Vor allem Regex-Literals `/.../` sind tueckisch, weil `//` darin vorkommen kann — der Tokenizer muss Kontext kennen.
- BSD-Klammern (Decision 0004) sind in JS gegeben — kein Parser-Problem, nur erwaehnt.

## Done

(append after work)
