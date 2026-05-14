# 0001 — Nim-Marker-Konvention + Architektur-Festlegung

Blocks: 0002, 0003, 0004

## Done when

- Marker-Konvention fuer Nim festgelegt und in `app/_share/spec_parser/README.md` dokumentiert: `## @spec` ... `## @end-spec` (Nim nutzt `#` als Zeilenkommentar, doppeltes `##` ist der Doc-Comment-Marker — passt zur "Spec ist Doku"-Idee). Begruendung im README.
- Granularitaet definiert (analog M005): Datei-Header, `type`/`object`/`enum`-Definitionen, `proc`/`func`/`method`/`iterator`/`template`/`macro`, Top-Level `const`/`let`/`var`, Object-Felder, lokale Spec-Bloecke.
- "Direkt darauffolgend"-Regel fuer Nim definiert: was darf zwischen Spec-Block und Symbol stehen (Whitespace, Doc-Comments, Pragmas). Aufgenommen ins README.
- Stub `app/_share/spec_parser/nim_parser.php` mit Funktion `nim_parser::parse(string $path): array` (Stub-Rueckgabe `["file_spec"=>[], "symbols"=>[], "warnings"=>[]]`).
- Sprach-Dispatch in `app/_share/spec_parser/spec_parser.php`: Endung `.nim` -> `nim_parser::parse()`. CLI-Smoketest gegen ein leeres Mini-`.nim`-File liefert `{"file":..., "language":"nim", "file_spec":[], "symbols":[], "warnings":[]}`.
- README ergaenzt: Nim-Sektion mit Marker, Granularitaet, Beispiel-Snippet (handgeschriebener Ziel-Output fuer 0002).
- Parser-Strategie-Festlegung: PHP-seitiger State-Machine-Tokenizer (analog JS/M005-Strategie), keine externen Libs, kein Subprozess. Begruendung im README.
- `@spec`-Bloecke an neuen PHP-Dateien.
- `php -l` sauber.

## Out of scope

- Echter Parser — 0002.
- Fixture-Tests — 0003.
- UI / file.php — 0004.

## Done

(append after work)
