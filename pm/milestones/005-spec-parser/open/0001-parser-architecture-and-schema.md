# 0001 — Parser-Architektur und JSON-Schema festlegen

See: pm/ideas/spec-as-comment.md, pm/decisions/0006-spec-parser.md
Blocks: 0002, 0003, 0004, 0005

## Done when

- Ein neues Verzeichnis `app/_share/spec_parser/` ist angelegt.
- Eine `app/_share/spec_parser/README.md` (oder gleichwertiger Spec-als-Kommentar-Block in einer `index.php`) beschreibt:
  - Eingabe: ein Dateipfad.
  - Ausgabe: JSON nach dem in `pm/ideas/spec-as-comment.md` skizzierten Schema (`file`, `file_spec`, `symbols[]` mit `kind`, `name`, `signature`/`type`, `spec[]`, ggf. `members[]`).
  - Sprach-Dispatch: Endung `.php` -> PHP-Parser, `.js` -> JS-Parser; alles andere wird abgelehnt (`{"error": "unsupported language"}`).
  - Keine Regex fuer Token-/Symbol-Erkennung. PHP nutzt `token_get_all`, JS nutzt eine vendored Mini-Lib (Acorn oder Esprima, in `app/_share/vendor/js/`) oder einen handgeschriebenen Tokenizer — Festlegung in diesem Ticket dokumentieren.
- Schema-Beispiel als JSON inline im README, gefuettert aus dem Pilot (`User.php`, `CreateUserAction.php` aus M004).
- Entscheidung: Wie wird der Parser aufgerufen? CLI (`php app/_share/spec_parser/parse.php <path>`) oder als Funktion (`spec_parser::parse($path)`) oder beides? Im README begruenden.
- `php -l` sauber auf allen neu angelegten PHP-Dateien.
- Smoketest: Aufruf gegen `User.php` aus dem Pilot liefert syntaktisch valides JSON (auch wenn der eigentliche PHP-Parser noch leer ist und nur `file`+`file_spec: []` liefert — Ticket 0002 fuellt ihn).

## Aus der Idea wichtig

- **Kein Regex** ist eine harte Decision (0006). Das Ticket darf keine Regex-Workarounds einbauen, auch nicht "nur fuer den Spec-Block-Marker".
- Spec-Block bezieht sich immer auf das **direkt darauffolgende Symbol**. Definiere im README, was "direkt darauffolgend" praezise heisst (naechstes nicht-Whitespace, nicht-Kommentar Token? Oder erlaubt zwischen Spec und Symbol nur Whitespace?).
- Spec wiederholt KEINE Typen. Parser uebernimmt Typ aus Code, nicht aus Spec-Text.

## Done

(append after work)
