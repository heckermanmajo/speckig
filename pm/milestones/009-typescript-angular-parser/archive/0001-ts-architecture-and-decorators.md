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

- **Marker-Konvention** in `app/_share/spec_parser/README.md` (neue
  Sektion `## TypeScript`): `// @spec` ... `// @end-spec` als TS-Zeilen-
  Kommentar — exakt wie JS, weil TS ein JS-Superset ist. JSDoc-Bloecke
  `/** ... */` bleiben unangetastet und werden NICHT als Spec gewertet.
  Beispiel-Snippet (Angular-Komponente mit `@Component`, `@LogCall()`,
  Property + Methode mit Spec) im README.
- **Granularitaet** im README dokumentiert: Datei-Header,
  `class`/`interface`/`enum`/`type`-Aliase, Klassen-Member (Properties,
  Methoden, Konstruktor, Getter, Setter), Top-Level
  `function`/`const`/`let`/`var`, Decorator-tragende Symbole
  (`@Component`, `@Injectable`, `@Directive`, custom). Lokale Spec-
  Bloecke innerhalb eines Funktions-/Methoden-Body als
  `kind: "local"`.
- **`kind`-Werte fuer TS**: `class`, `interface`, `enum`, `type`,
  `function`, `method`, `constructor`, `property`, `getter`, `setter`,
  `const`, `let`, `var`, `local`. Tabelle im README. Im Vergleich zur
  JS-Liste neu: `interface`, `enum`, `type`, `constructor`. Begruendung
  fuer eigenen `constructor`-`kind`: TS-Konstruktor kann
  Parameter-Properties via `public`/`private`-Modifier deklarieren —
  semantisch deutlich genug, dass Renderer ihn von normalen Methoden
  unterscheiden koennen muessen.
- **Decorator-Behandlung — Schema-Erweiterung am Symbol**: neues
  optionales Feld `decorators[]` (Default `[]`). Form
  `[{name: string, args_source: string|null}]`. `args_source`-Semantik
  pragmatisch in drei Faellen festgelegt und im README ausformuliert:
  - `null` wenn der Decorator ohne Klammern steht: `@Override` ->
    `{name: "Override", args_source: null}`.
  - `""` wenn die Klammern leer sind: `@LogCall()` ->
    `{name: "LogCall", args_source: ""}`.
  - `"<inhalt>"` wenn Klammern Inhalt haben: `@Component({selector:'app-root'})`
    -> `{name: "Component", args_source: "{selector:'app-root'}"}`.
  Begruendung fuer `null` vs. `""`: ein Renderer kann die zwei TS-
  Formen sinnvoll unterscheiden — `@Foo` verwendet die Funktion direkt,
  `@Foo()` ruft eine Decorator-Factory mit leerem Aufruf auf. Wir geben
  beide Informationen weiter, statt sie schon im Parser zu
  vermischen. `args_source`-Inhalt wird **nicht** semantisch geparst
  (kein JSON-Decode, kein TS-Parse). V1 unterstuetzt **nur Symbol-Level-
  Decorators** (Klassen/Methoden/Properties/Konstruktoren/Setter/Getter)
  — Parameter-Decorators laufen tokenmaessig in den Signatur-Source-
  String. Schema-Erweiterung ist andere-Sprachen-tolerant: PHP, JS,
  Nim, Lua emittieren das Feld nicht; Renderer (M009/0004 + ggf. M010)
  generalisieren ueber das optionale Feld. Die
  `## Symbol-Objekt`-Tabelle wurde um eine `decorators`-Zeile (TS-only,
  optional) erweitert.
- **"Direkt darauffolgend"-Regel** fuer TS definiert: zwischen Spec-
  Block und Symbol erlaubt sind Whitespace, Block-Kommentare,
  JSDoc-Bloecke, andere `// ...`-Kommentare (nicht-Spec), beliebig
  viele Decorators (`@Foo`, `@Bar(...)`) und Modifier-Keywords
  (`export`, `default`, `async`, `static`, `readonly`, `public`,
  `protected`, `private`, `abstract`, `declare`, `override`).
  Dangling-Verhalten (`"dangling spec at line N"`) wie bei den anderen
  Sprachen.
- **Parser-Strategie** im README festgelegt: PHP-seitiger State-
  Machine-Tokenizer (analog M005/0001 fuer JS, M007/0001 fuer Nim,
  M008/0001 fuer Lua), kein Regex (Decision 0006), keine externen Libs,
  kein Subprozess (insbesondere kein Node + TS-Compiler-API — Decision
  0004 schliesst npm/Node-Abhaengigkeit aus). Generics
  (`<T extends Foo>`) werden tokenmaessig durchgelaufen und landen als
  Source-String in der Signatur. Mapped Types, Conditional Types,
  Type-Inference, Namespace-Bloecke sind out-of-scope.
- **Edge-Cases** im README aufgefuehrt: `// @spec`-Text in einzeiligen
  Strings (`'...'`, `"..."`), Template-Literals (`` `...` `` mit
  `${...}`-Interpolation inkl. verschachtelten Strings), Regex-Literals
  (kontextabhaengige Erkennung wie in JS), Block-Kommentaren
  (`/* ... */`) und JSDoc-Bloecken (`/** ... */`) wird NICHT als
  Marker erkannt. Marker greift nur bei Token-Klasse `comment_line`.
- **Beispiel-Snippet** (handgeschriebener Ziel-Output fuer M009/0002)
  im README: Angular-AppComponent mit `@Component({...})` an der Klasse,
  `@LogCall()` an der Methode, ein Property `user` ohne Decorator,
  inkl. JSON-Output mit `decorators[]` an Klasse + Methode + leerem
  Array am Property. Zeigt alle drei `args_source`-Faelle (mit Inhalt
  beim `@Component`, leer beim `@LogCall()`, hypothetischer null-Fall
  in den Anmerkungen erwaehnt).
- **Sprach-Dispatch-Tabelle** im README erweitert: Zeile fuer `.ts`
  -> `ts_parser::parse()` (Hinweis "PHP-seitiger Tokenizer (M009/0002)").
- **Stub** `app/_share/spec_parser/ts_parser.php` angelegt:
  `ts_parser::parse(string $path): array` liefert
  `["file_spec"=>[], "symbols"=>[], "warnings"=>[]]` fuer existierende
  Dateien, `["warnings" => ["file not found: ..."]]` sonst. Klassen-
  Name `ts_parser` lowercase nach Decision 0003. Namespace
  `_share\spec_parser`. Header-Spec (Datei + Klasse + parse-Methode)
  konsistent mit `lua_parser.php` / `nim_parser.php`. Header-Spec
  erwaehnt explizit die `decorators[]`-Schema-Erweiterung und die
  `args_source`-Semantik.
- **Dispatcher** `app/_share/spec_parser/spec_parser.php` erweitert:
  `require_once .../ts_parser.php` ergaenzt; `if ($extension === "ts")`-
  Block analog zu `.lua`-Block; Doc-Spec der `parse()`-Methode erwaehnt
  jetzt auch `.ts`. Dispatcher-Form bleibt if-Kette (M005-Stil, kein
  Map-Lookup).
- **Files in diesem Verzeichnis**-Tabelle im README um `ts_parser.php`-
  Zeile erweitert; Beschreibung der `README.md`-Zeile auch um
  TS-Strategie ergaenzt.

### Verifikations-Belege

- `php -l app/_share/spec_parser/ts_parser.php` -> "No syntax errors detected".
- `php -l app/_share/spec_parser/spec_parser.php` -> "No syntax errors detected".
- `php app/_share/spec_parser/index.php /tmp/ts_smoke.ts` -> exit 0:

  ```
  {
      "file": "/tmp/ts_smoke.ts",
      "language": "ts",
      "file_spec": [],
      "symbols": [],
      "warnings": []
  }
  ```

  (`/tmp/ts_smoke.ts`-Inhalt: `console.log("hello");`)

- `php app/_share/spec_parser/tests/run.php` -> `27/27 passed`, exit 0
  (php: 6/6, js: 4/4, nim: 7/7, lua: 8/8, plus synthetische Tests
  fuer Vendor-Blacklist und unsupported-language). Keine Regression.
- `grep -n "## TypeScript" app/_share/spec_parser/README.md` -> `561:## TypeScript`.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Temp-File `/tmp/ts_smoke.ts` nach Smoketest aufgeraeumt.
- Out-of-scope eingehalten: kein echter TS-Parser implementiert
  (M009/0002), keine Fixtures (M009/0003), kein `app/file.php`-Touch
  (M009/0004), kein `.tsx`, kein Renderer-Touch, keine andere Sprache
  angefasst, README ausserhalb der neuen `## TypeScript`-Sektion und
  der Dispatch-/Files-/Symbol-Tabelle unveraendert.
