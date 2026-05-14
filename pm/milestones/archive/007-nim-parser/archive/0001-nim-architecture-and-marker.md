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

- **Marker-Konvention** in `app/_share/spec_parser/README.md` (neue
  Sektion `## Nim`): `## @spec` ... `## @end-spec` als Doc-Comment-
  Zeilen. Begruendung im README: Nim hat `#` als Zeilenkommentar, `##`
  ist der Doc-Comment-Marker. "Spec ist Doku" -> `##`-Variante ist
  semantisch passend. Beispiel-Snippet im README.
- **Granularitaet** im README dokumentiert: Datei-Header, `type`/
  `object`/`enum`/`tuple`, `proc`/`func`/`method`/`iterator`/`template`/
  `macro`, Top-Level-`const`/`let`/`var`, Object-Felder, lokale
  Spec-Bloecke innerhalb eines Routine-Body.
- **`kind`-Werte fuer Nim**: `proc`, `func`, `method`, `iterator`,
  `template`, `macro`, `type`, `object`, `enum`, `field`, `const`,
  `let`, `var`, `local`. Tabelle im README. Keine Uebersetzung in das
  PHP/JS-Vokabular — Nim bekommt seine eigenen `kind`-Strings.
- **"Direkt darauffolgend"-Regel** fuer Nim definiert: Whitespace,
  nicht-Spec-`##`-Doc-Kommentare, normale `#`-Kommentare und
  Block-Kommentare `#[ ... ]#` duerfen zwischen Spec-Block und Symbol
  stehen. Pragmas (`{.async.}`, `{.inline.}`) gehoeren zur
  Symbol-Signatur, nicht "dazwischen". Dangling-Verhalten wie bei
  PHP/JS.
- **Indent-basierte Bloecke**: V1-Verhalten dokumentiert
  (Spalten-Zaehlung fuer Object-Felder und lokale Spec-Bloecke).
- **Parser-Strategie** im README festgelegt: PHP-seitiger State-Machine-
  Tokenizer, kein Regex (Decision 0006), keine externen Libs, kein
  Subprozess. Subset, den V1 NICHT semantisch versteht: Nim-Macros,
  generische Procs (Generics werden tokenmaessig durchgelaufen).
- **Edge-Cases** im README aufgefuehrt: `## @spec` in Strings (`"..."`,
  `"""..."""`, `r"..."`) und Block-Kommentaren `#[ ... ]#` wird NICHT
  als Marker erkannt.
- **Beispiel-Snippet** (handgeschriebener Ziel-Output fuer M007/0002)
  im README: Nim-File mit Datei-Spec, `type Point = object` mit zwei
  Felder-Specs, `proc distance(a, b: Point): float = ...` mit Spec
  inkl. Conditions. JSON-Output inline, gleichem Schema wie die
  PHP/JS-Beispiele.
- **Sprach-Dispatch-Tabelle** im README erweitert: Zeile fuer `.nim`
  -> `nim_parser::parse()`.
- **Stub** `app/_share/spec_parser/nim_parser.php` angelegt:
  `nim_parser::parse(string $path): array` liefert
  `["file_spec"=>[], "symbols"=>[], "warnings"=>[]]` fuer existierende
  Dateien, `["warnings" => ["file not found: ..."]]` sonst. Klassen-
  Name `nim_parser` lowercase nach Decision 0003. Namespace
  `_share\spec_parser`. Header-Spec (Datei + Klasse + parse-Methode)
  konsistent mit `php_parser.php`/`js_parser.php`.
- **Dispatcher** `app/_share/spec_parser/spec_parser.php` erweitert:
  `require_once .../nim_parser.php` ergaenzt; `if ($extension === "nim")`-
  Block analog zu `.js`-Block; Doc-Spec der `parse()`-Methode erwaehnt
  jetzt auch `.nim`. Dispatcher-Form bleibt if-Kette (M005-Stil, kein
  Map-Lookup).
- **Files in diesem Verzeichnis**-Tabelle im README um `nim_parser.php`-
  Zeile erweitert.

### Verifikations-Belege

- `php -l app/_share/spec_parser/nim_parser.php` -> "No syntax errors detected".
- `php -l app/_share/spec_parser/spec_parser.php` -> "No syntax errors detected".
- `php app/_share/spec_parser/index.php /tmp/nim_smoke.nim` -> exit 0:

  ```
  {
      "file": "/tmp/nim_smoke.nim",
      "language": "nim",
      "file_spec": [],
      "symbols": [],
      "warnings": []
  }
  ```

- `php app/_share/spec_parser/index.php /tmp/nonexistent.unknown` -> exit 2:

  ```
  {
      "file": "/tmp/nonexistent.unknown",
      "error": "unsupported language",
      "extension": "unknown"
  }
  ```

- `php app/_share/spec_parser/tests/run.php` -> `12/12 passed`, exit 0
  (bestehende PHP/JS-Fixtures + synthetische Tests bleiben gruen).
- `grep -n "## Nim" app/_share/spec_parser/README.md` -> `100:## Nim`.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Temp-File `/tmp/nim_smoke.nim` nach Smoketest aufgeraeumt.
- Out-of-scope eingehalten: kein echter Nim-Parser implementiert
  (M007/0002), keine Fixtures (M007/0003), kein `app/file.php`-Touch
  (M007/0004), keine andere Sprache angefasst, README ausserhalb der
  neuen `## Nim`-Sektion und der Dispatch-/Files-Tabelle unveraendert.
