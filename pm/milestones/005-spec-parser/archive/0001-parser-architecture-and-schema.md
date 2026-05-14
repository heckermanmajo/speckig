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

- Verzeichnis `app/_share/spec_parser/` angelegt mit:
  - `index.php` — CLI-Einstieg, nimmt Pfad-Arg, gibt JSON, Exit-Codes
    0 (ok) / 1 (Aufruf-Fehler) / 2 (Schema-Fehler: vendor /
    unsupported language).
  - `spec_parser.php` — Sprach-agnostischer Dispatcher, Funktion
    `\_share\spec_parser\spec_parser::parse(string $path): array`.
    Lehnt vendored Pfade und unbekannte Endungen ab, dispatcht `.php`
    an `php_parser::parse`, `.js` an `js_parser::parse`.
  - `php_parser.php` — Stub, gibt heute leeres Schema. Schnittstelle
    festgelegt: `parse(string $path): array` mit `file_spec`,
    `symbols`, `warnings`. M005/0002 fuellt mit `token_get_all`-Logik.
  - `js_parser.php` — Stub, gleiche Schnittstelle. M005/0003 fuellt.
  - `README.md` — Schema-Doku (Top-Level + Symbol-Felder),
    Aufruf-Form, Sprach-Dispatch, JS-Strategie-Festlegung,
    "Direkt darauffolgend"-Definition, Beispiel-JSON fuer beide
    Pilot-Dateien aus M004.
- **Aufruf-Form-Entscheidung:** Beides — CLI fuer Tests (M005/0004) und
  Ad-hoc-Debugging, Funktion fuer die UI-Integration (M005/0005). Der
  CLI ist ein duenner Wrapper um die Funktion (`json_encode` + Exit-Code).
- **JS-Parser-Strategie-Entscheidung:** PHP-seitiger State-Machine-
  Tokenizer-Walker. Kein Node-Subprozess, keine vendored JS-Lib.
  Begruendung im README: "kein npm" (Decision 0004) ist hart; der noetige
  JS-Subset (Top-Level-Decls + Klassen-Member, Edge-Cases bei Regex- und
  Template-Literals) ist klein genug fuer einen handgeschriebenen
  Tokenizer. Falls in M005/0003 zu fragil, ist Acorn-Vendoring eine
  spaetere Decision.
- **PHP-Parser-Strategie:** `token_get_all` (Built-in) — keine Diskussion
  noetig, Decision 0006 schreibt es vor.
- Spec-Format eingehalten ("eat your own dogfood"): alle vier neuen
  PHP-Dateien tragen `// @spec ... // @end-spec`-Bloecke. Klassen
  `lowercase` (Decision 0003 — statische Funktionsbuendel), Methoden
  `snake_case`, BSD-Klammern, `declare(strict_types=1)`, Indent 4.
- **Smoketest-Belege:**
  - `php -l` sauber auf allen vier neuen PHP-Dateien.
  - `php app/_share/spec_parser/index.php app/user/data/User.php`
    -> exit 0, JSON `{"file":"app/user/data/User.php","language":"php",
    "file_spec":[],"symbols":[],"warnings":[]}` (Stub liefert leere
    Arrays — erwartet, M005/0002 fuellt sie).
  - `php app/_share/spec_parser/index.php app/user/actions/CreateUserAction.php`
    -> exit 0, analog.
  - `php app/_share/spec_parser/index.php some.css` -> exit 2, JSON
    `{"file":"some.css","error":"unsupported language","extension":"css"}`.
  - `php app/_share/spec_parser/index.php app/_share/vendor/Parsedown.php`
    -> exit 2, JSON `{"file":"app/_share/vendor/Parsedown.php",
    "error":"vendor code not parsed"}`.
  - JSON ist parsbar via
    `... | php -r 'var_dump(json_decode(file_get_contents("php://stdin"), true));'`.
  - Funktions-Aufruf: `php -r 'require "app/_share/spec_parser/spec_parser.php";
    var_export(\_share\spec_parser\spec_parser::parse("app/user/data/User.php"));'`
    gibt das erwartete Array zurueck.
- Out-of-scope eingehalten: kein Touch an `app/file.php`, keine andere
  Code-Datei geaendert, keine `.spec`-Dateien angefasst, kein echter
  PHP-/JS-Parser implementiert (das ist 0002/0003), keine Fixtures
  (das ist 0004), `pm/how-to/spec.md` und Decision 0005 unangetastet.
- Streu-File-Check: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Kein `php -S` gestartet — UI-Anbindung ist 0005.
