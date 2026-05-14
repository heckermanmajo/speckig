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

- **Marker-Konvention** in `app/_share/spec_parser/README.md` (neue
  Sektion `## Groovy`): `// @spec` ... `// @end-spec` als Groovy-Zeilen-
  Kommentar — exakt wie JS/TS, weil Groovy Javas/JavaScripts Kommentar-
  Syntax eins-zu-eins erbt. Groovydoc-Bloecke `/** ... */` bleiben
  unangetastet und werden NICHT als Spec gewertet. Beispiel-Snippet
  (Spring-Boot-RestController mit `@RestController`,
  `@RequestMapping("/api")`, `@Autowired`-Field, `@GetMapping("/foo/{id}")`-
  Methode, alle mit Spec-Bloecken) im README.
- **Granularitaet** im README dokumentiert: Datei-Header,
  `class`/`interface`/`enum`/`trait`, Klassen-Member (Felder, Methoden,
  Konstruktoren, statische Methoden, Properties), Top-Level Skript-
  Variablen und -Methoden (Groovy-Skripte ohne Klassen-Wrapper),
  Annotation-tragende Symbole, lokale Spec-Bloecke innerhalb eines
  Methoden-/Skript-Body als `kind: "local"`.
- **`kind`-Werte fuer Groovy**: `class`, `interface`, `enum`, `trait`,
  `method`, `constructor`, `field`, `property`, `script-var`,
  `script-method`, `local`. Tabelle im README. Begruendung fuer die
  `field`-vs.-`property`-Unterscheidung (Modifier-tragende Felder vs.
  Groovy-Properties mit Auto-Getter/Setter) dort ausformuliert.
  `script-var`/`script-method` deckt Groovy-Skripte ohne
  `class`-Wrapper ab. `constructor` bekommt eigenen `kind`, damit
  Renderer Konstruktoren von normalen Methoden trennen koennen.
- **Annotation-Behandlung — Schema-Erweiterung am Symbol**: neues
  optionales Feld `annotations[]` (Default `[]`). Form
  `[{name: string, args_source: string|null}]` — strukturell identisch
  zur TS-`decorators[]`-Form (M009/0001). `args_source`-Semantik
  identisch zu TS:
  - `null` wenn die Annotation ohne Klammern steht: `@RestController` ->
    `{name: "RestController", args_source: null}`.
  - `""` wenn Klammern leer sind: `@Service()` ->
    `{name: "Service", args_source: ""}`.
  - `"<inhalt>"` wenn Klammern Inhalt haben: `@RequestMapping("/api")`
    -> `{name: "RequestMapping", args_source: "\"/api\""}`.
  Begruendung im README, warum wir das Feld `annotations[]` und nicht
  `decorators[]` nennen: semantische Treue zur Sprache (Java-Annotations
  vs. TS-Decorators), aber UI-Renderer-kompatibel ueber den
  Generalisierungs-Fallback `decorators || annotations`, den M009/0004
  bereits in `app/_share/js/content_loader.js` hat. V1 erkennt nur
  Symbol-Level-Annotations; Parameter-Annotations
  (`@PathVariable Long id`) laufen tokenmaessig in den Signatur-
  Source-String. Schema-Erweiterung ist andere-Sprachen-tolerant: PHP,
  JS, Nim, Lua, TS emittieren das Feld nicht. Die `## Symbol-Objekt`-
  Tabelle wurde um eine `annotations`-Zeile (Groovy-only, optional)
  erweitert.
- **"Direkt darauffolgend"-Regel** fuer Groovy definiert: zwischen Spec-
  Block und Symbol erlaubt sind Whitespace, Block-Kommentare,
  Groovydoc-Bloecke, andere `// ...`-Kommentare (nicht-Spec), beliebig
  viele Annotations (`@Foo`, `@Bar(...)`) und Modifier-Keywords
  (`public`, `protected`, `private`, `static`, `final`, `abstract`,
  `def`). Dangling-Verhalten (`"dangling spec at line N"`) wie bei den
  anderen Sprachen.
- **Parser-Strategie** im README festgelegt: PHP-seitiger State-
  Machine-Tokenizer (analog M005/0001 fuer JS, M007/0001 fuer Nim,
  M008/0001 fuer Lua, M009/0001 fuer TS), kein Regex (Decision 0006),
  keine externen Libs, kein Subprozess (insbesondere kein
  `groovyc`/Groovy-AST). Decision 0004 ("kein npm, kein Bundler, kein
  TypeScript") wird symmetrisch auf Java/Groovy-JVM ausgeweitet.
- **Edge-Cases** im README aufgefuehrt: `// @spec`-Text in einzeiligen
  Strings (`'...'`, `"..."`), Triple-Strings (`'''...'''`,
  `"""..."""`), GStrings mit `${...}`-Interpolation und `$var`-Form,
  Block-Kommentaren (`/* ... */`) und Groovydoc-Bloecken
  (`/** ... */`) wird NICHT als Marker erkannt. Marker greift nur bei
  Token-Klasse `comment_line`. Closures, GString-Interpolation und
  Operator-Overloading laufen tokenmaessig durch, werden NICHT
  semantisch verstanden — explizit dokumentiert.
- **Beispiel-Snippet** (handgeschriebener Ziel-Output fuer M010/0002)
  im README: Spring-Boot-RestController `FooController` mit
  `@RestController @RequestMapping("/api")` an der Klasse,
  `@Autowired`-Field `repo` (kind `property` wegen fehlendem Modifier),
  `@GetMapping("/foo/{id}")`-Methode `getFoo`. JSON-Output zeigt alle
  drei `args_source`-Faelle und macht klar, dass `@PathVariable` als
  Parameter-Annotation NICHT in `annotations[]` landet, sondern Teil
  der Signatur bleibt.
- **Sprach-Dispatch-Tabelle** im README erweitert: Zeile fuer
  `.groovy` -> `groovy_parser::parse()` (Hinweis "PHP-seitiger
  Tokenizer (M010/0002)").
- **Stub** `app/_share/spec_parser/groovy_parser.php` angelegt:
  `groovy_parser::parse(string $path): array` liefert
  `["file_spec"=>[], "symbols"=>[], "warnings"=>[]]` fuer existierende
  Dateien, `["warnings" => ["file not found: ..."]]` sonst. Klassen-
  Name `groovy_parser` lowercase nach Decision 0003. Namespace
  `_share\spec_parser`. Header-Spec (Datei + Klasse + parse-Methode)
  konsistent mit `ts_parser.php` / `lua_parser.php`. Header-Spec
  erwaehnt explizit die `annotations[]`-Schema-Erweiterung, die
  `args_source`-Semantik und die Begruendung fuer
  `annotations[]`-vs.-`decorators[]`-Naming.
- **Dispatcher** `app/_share/spec_parser/spec_parser.php` erweitert:
  `require_once .../groovy_parser.php` ergaenzt;
  `if ($extension === "groovy")`-Block analog zu `.ts`-Block; Doc-Spec
  der `parse()`-Methode erwaehnt jetzt auch `.groovy`. Dispatcher-Form
  bleibt if-Kette (M005-Stil, kein Map-Lookup).
- **Files in diesem Verzeichnis**-Tabelle im README um
  `groovy_parser.php`-Zeile erweitert; Beschreibung der `README.md`-
  Zeile auch um Groovy-Strategie ergaenzt.

### Verifikations-Belege

- `php -l app/_share/spec_parser/groovy_parser.php` -> "No syntax errors detected".
- `php -l app/_share/spec_parser/spec_parser.php` -> "No syntax errors detected".
- `php app/_share/spec_parser/index.php /tmp/groovy_smoke.groovy` -> exit 0:

  ```
  {
      "file": "/tmp/groovy_smoke.groovy",
      "language": "groovy",
      "file_spec": [],
      "symbols": [],
      "warnings": []
  }
  ```

  (`/tmp/groovy_smoke.groovy`-Inhalt: `println("hello")`)

- `php app/_share/spec_parser/tests/run.php` -> `38/38 passed`, exit 0
  (php: 6/6, js: 4/4, nim: 7/7, lua: 8/8, ts: 11/11, plus 2
  synthetische Tests fuer Vendor-Blacklist und unsupported-language).
  Keine Regression — `.groovy` ist im Test-Runner noch nicht
  registriert (Fixture-Tests folgen in M010/0003).
- `grep -n "## Groovy" app/_share/spec_parser/README.md` -> `908:## Groovy`.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` zeigt nur `./app.sqlite` (kanonisch).
- Temp-File `/tmp/groovy_smoke.groovy` nach Smoketest aufgeraeumt.
- Out-of-scope eingehalten: kein echter Groovy-Parser implementiert
  (M010/0002), keine Fixtures (M010/0003), kein `app/file.php`-Touch
  (M010/0004), kein Java-Parser, kein Renderer-Touch (M009/0004 hat
  bereits `decorators || annotations`-Fallback in
  `app/_share/js/content_loader.js`), keine andere Sprache angefasst,
  README ausserhalb der neuen `## Groovy`-Sektion und der
  Dispatch-/Files-/Symbol-Tabelle unveraendert.
