# 0001 — Groovy-Marker-Konvention + Annotation-Strategie

Blocks: 0002, 0003, 0004

## Done when

- Marker-Konvention fuer Groovy festgelegt und in `app/_share/spec_parser/README.md` dokumentiert: `// @spec` ... `// @end-spec`. Bestehende Groovydoc-Bloecke (`/** ... */`) bleiben unangetastet.
- Granularitaet definiert: Datei-Header, `class`/`interface`/`enum`/`trait`, Klassen-Member (Felder, Methoden, Konstruktoren, statische Methoden), Top-Level Skript-Variablen und -Methoden (Groovy erlaubt Top-Level Code), Annotation-tragende Symbole.
- Annotation-Behandlung **analog Decorators in M009/0001**: Spec-Block + Annotations + Symbol = Spec gehoert zum Symbol, Annotations als `annotations: [{name, args_source}]` mitgefuehrt. Schema-Erweiterung gleich wie TS (Wiederverwendung des `decorators[]`-Felds oder eigenes `annotations[]`-Feld — entscheide pragmatisch und dokumentier; Vorschlag: `annotations[]` als eigenes Feld mit gleicher Struktur, weil semantisch verschieden, aber UI-Renderer kann beide gleich behandeln).
- Spring-Boot-Pattern-Doku: Beispiel-Snippet `@RestController @RequestMapping("/api") class FooController { @GetMapping("/foo") def getFoo() {...} }` mit handgeschriebenem Ziel-Output (Annotations + Args als Source-String).
- Stub `app/_share/spec_parser/groovy_parser.php` mit `groovy_parser::parse()` (leeres Schema).
- Sprach-Dispatch: `.groovy` -> `groovy_parser::parse()`. CLI-Smoketest.
- Parser-Strategie: PHP-seitiger State-Machine-Tokenizer. Groovy-Eigenheiten die V1 NICHT semantisch versteht aber tokenmaessig durchlaeuft: Closures (`{ -> ... }`), GString-Interpolation (`"text $var ${expr}"`), Operator-Overloading. Doku im README.
- README ergaenzt: Groovy-Sektion.
- `@spec`-Bloecke an neuen PHP-Dateien.
- `php -l` sauber.

## Out of scope

- Java (`.java`).
- Echter Parser — 0002.
- Fixture-Tests — 0003.
- UI / file.php — 0004.

## Done

(append after work)
