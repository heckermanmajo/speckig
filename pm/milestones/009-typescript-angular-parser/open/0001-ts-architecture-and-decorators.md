# 0001 — TS-Marker-Konvention + Decorator-Strategie

Blocks: 0002, 0003, 0004

## Done when

- Marker-Konvention fuer TS festgelegt und in `app/_share/spec_parser/README.md` dokumentiert: `// @spec` ... `// @end-spec` (gleiches wie JS — TS ist Superset). Bestehende JSDoc-Bloecke (`/** ... */`) bleiben unangetastet, werden NICHT als Spec interpretiert.
- Granularitaet definiert: Datei-Header, `class`/`interface`/`enum`/`type`-Aliase, Klassen-Member (Properties, Methoden, Konstruktor), Top-Level `function`/`const`/`let`/`var`, Decorator-tragende Symbole (`@Component`, `@Injectable`, `@Directive`, custom).
- "Direkt darauffolgend"-Regel: Spec-Block + dann Decorator(s) + dann Klasse — Spec gehoert zur Klasse, nicht zum Decorator. Decorators selbst werden als Strukturinfo am Symbol mitgefuehrt (`decorators: ["Component", "Injectable"]` oder reicher: `decorators: [{name, args_source}]` — entscheide im README).
- Angular-Pattern-Doku: Beispiel-Snippet `@Component({selector:'app-root', templateUrl:'...'}) export class AppComponent {}` mit handgeschriebenem Ziel-Output (Decorator-Args als Source-String mitgefuehrt, KEIN JSON-Parse der Decorator-Args).
- Stub `app/_share/spec_parser/ts_parser.php` mit `ts_parser::parse()` (leeres Schema).
- Sprach-Dispatch: `.ts` -> `ts_parser::parse()`. CLI-Smoketest gegen leere `.ts`-Datei liefert valides JSON.
- Parser-Strategie: PHP-seitiger State-Machine-Tokenizer (analog JS). Kein Regex, keine Node-Subprozesse, keine vendored Lib in V1. Schwierige TS-Konstrukte die V1 NICHT abdecken muss: Generics-Syntax (`<T extends Foo>`), Mapped Types, Conditional Types — deren Token-Stream wird durchlaufen aber nicht semantisch verstanden. Doku im README.
- README ergaenzt: TS-Sektion mit Marker, Granularitaet, Decorator-Behandlung, Beispiel.
- `@spec`-Bloecke an neuen PHP-Dateien.
- `php -l` sauber.

## Out of scope

- Echter Parser — 0002.
- Fixture-Tests — 0003.
- UI / file.php — 0004.
- `.tsx`.
- Type-Inference.

## Done

(append after work)
