# 0002 — TS-Parser (PHP-seitiger Tokenizer mit Decorator-Support)

Blocked by: 0001

## Done when

- `app/_share/spec_parser/ts_parser.php` implementiert `ts_parser::parse()` nach Schema aus M005/0001, erweitert um `decorators[]` an Symbolen (Schema-Erweiterung in 0001 dokumentiert).
- PHP-seitiger State-Machine-Tokenizer. Strings (single, double, backtick template literals mit `${...}`-Interpolation), Regex-Literals (Kontext-Erkennung wie in JS), Block-Kommentare, JSDoc duerfen nie als Spec missverstanden werden.
- Spec-Erkennung: `// @spec` ... `// @end-spec` als Zeilen-Kommentare, dazwischenliegende `//`-Kommentare als Spec-Zeilen.
- Symbol-Erkennung:
  - `class`/`interface`/`enum`/`type` Top-Level + exportiert.
  - `function`/`const`/`let`/`var` Top-Level.
  - Klassen-Member: Properties (mit Typ-Annotation, optional Default), Methoden, Konstruktor, get/set Accessoren.
  - Decorators (`@Foo`, `@Foo(...)`, `@Foo({...})`) gehoeren zum naechsten Symbol — Spec-Block + Decorator-Liste + Symbol = Spec-Block bezieht sich aufs Symbol, Decorator-Liste wird als Strukturinfo angehaengt.
- Signatur als Source-String inkl. Generics (verbatim aus dem Source uebernommen, keine Parse-Tiefe), Parameter-Typen, Defaults, Return-Typ.
- Decorator-Args bleiben als Source-String erhalten (keine JSON-Interpretation): `decorators: [{name: "Component", args_source: "{selector:'app-root', templateUrl:'./app.component.html'}"}]`.
- `kind`-Werte: `class`, `interface`, `enum`, `type`, `function`, `method`, `constructor`, `property`, `getter`, `setter`, `const`, `let`, `var`, `local`.
- Existence-Check + Dangling-Spec-Warning wie bei den anderen Parsern.
- `php -l` sauber.

## Smoketest

- Mini-`.ts`-Fixture mit Angular-Component (Datei-Spec + Klassen-Spec + Decorator + Property mit Spec + Methode mit Spec inkl. Conditions). Manuell parsen, Output-Snippet im Done.

## Out of scope

- Type-Inference, Generics-Resolution.
- `.tsx`.
- Fixture-Test-Runner — 0003.
- Angular-Template-Parsing.

## Done

- **Tokenizer** (`ts_parser::tokenize`): State-Machine-Walker analog
  `js_parser` mit allen JS-Token-Klassen (whitespace, comment_line,
  comment_block, string_single/double, template_literal mit
  `${...}`-Interpolation und korrektem Escape/Newline-Handling,
  regex_literal mit Kontext-Heuristik, number, identifier, punctuation).
  TS-spezifisch: `@`, `?`, `!`, `<`, `>` werden als Single-Char-
  Punctuation emittiert; `?.` bleibt als Two-Char-Op (bestehende
  JS-Liste). Multi-Char-Operatoren (`===`, `>>>`, `=>`, `?.`, etc.)
  wie in `js_parser`.

- **Walker** (`ts_parser::walk_tokens`): Top-Level-Loop mit
  Depth-Tracking (nur Spec-Bloecke auf `depth === 0` zaehlen). Sammelt
  `pending_spec` und `pending_decorators` getrennt; beide werden ans
  naechste Symbol gebunden. Modifier-Identifier (`export`, `default`,
  `async`, `static`, `readonly`, `public`, `protected`, `private`,
  `abstract`, `declare`, `override`) werden weggeschluckt. Symbol-
  Dispatch fuer `function`/`class`/`interface`/`enum`/`type`/
  `const`/`let`/`var`. `type` wird nur als Type-Alias gewertet wenn
  ein Identifier folgt (sonst durchlaufen — `type` koennte ein
  Variablenname sein).

- **Datei-Header-Spec**: `first_decl_index` sucht den ersten
  Top-Level-Symbol-Marker (`function`/`class`/`interface`/`enum`/
  `type`/`const`/`let`/`var`/`import`/`export`) ODER ein Decorator-
  Punctuation-`@`. Spec-Bloecke davor wandern in `file_spec`
  (einmalig). Fuer den Smoketest bedeutet das: der erste
  `// @spec ... // @end-spec`-Block VOR dem `@Component` landet im
  `file_spec`, nicht an der Klasse — exakt wie das Ticket
  `Erwartet: file_spec: 1 Zeile` verlangt.

- **Decorator-Parsen** (`ts_parser::read_decorator`): Beginnt nach
  `@`. Liest Identifier, qualified `.`-Pfad (`Foo.Bar`), peekt auf
  `(`. Wenn `(` folgt: `read_balanced("(",")")` und der Inhalt
  zwischen den Klammern (Outer-Klammern abgeschnitten,
  `trim()`-applied) wird `args_source`. Sonst `args_source = null`.
  Drei-Faelle-Output exakt wie spezifiziert:
  - `@Component`        -> `{name:"Component", args_source: null}`
  - `@Component()`      -> `{name:"Component", args_source: ""}`
  - `@Component({...})` -> `{name:"Component", args_source: "{...}"}`
  Decorators werden im Walker gesammelt (`pending_decorators[]`) und
  sowohl an Top-Level-Symbole (`class`/`function`) als auch an
  Klassen-Member (Methoden/Properties etc.) angehaengt. Fuer
  Sprachen-/Symbol-Typen ohne sinnvollen Decorator-Slot
  (`interface`/`enum`/`type`/`const`/`let`/`var`) wird ein Decorator,
  der davor steht, V1-pragmatisch verworfen — `pending_decorators`
  wird beim Symbol-Match auf `[]` zurueckgesetzt.

- **Generic-vs-Less-Than-Heuristik**: `<` ist im Tokenizer immer
  Punctuation. Im Walker wird `<` nur in **Deklarations-Kontexten**
  als Generic-Start interpretiert: nach `class Name`, `interface Name`,
  `function name`, `type Alias`, sowie nach Member-Namen vor `(`. Die
  konkrete Erkennung steckt in `read_balanced_angles`, das ab `<`
  liest und mit Tiefen-Counter bis zum matched `>` (oder
  zerlegtem `>>`/`>>>`) avanciert. Whitespace im Generic wird zu
  Single-Spaces komprimiert. In allen anderen Kontexten
  (z.B. innerhalb von `read_expression_until_stop`) wird `<` weder
  angegriffen noch als Generic-Open behandelt — es wandert als
  Punctuation in den Source-String und der `angle_depth`-Counter
  schuetzt nur davor, dass ein `>` (das echtes Vergleichs-Op sein
  koennte) den Stop-Char-Mechanismus durcheinander bringt.

- **Type-Annotation-Verschlucken**: `read_optional_return_type`
  (nach Param-Liste) und der `:`-Branch in `try_read_class_member`
  / `read_var_decl` lesen ab `:` mit
  `read_expression_until_stop(stop_chars = ["{","=",";",","] / [...])
  bis zum naechsten Top-Level-Stop. Klammern-Tiefe und Generic-Angle-
  Tiefe werden ignoriert (geschachtelte `(...)`/`[...]`/`{...}` zaehlen
  korrekt). Result: `signature` enthaelt z.B.
  `"async login(username: string, password: string): Promise<void>"`,
  `property user` bekommt `type: "User | null"`, `default: "null"`.

- **Class-Body** (`read_class_body`): Sammelt Decorators und Specs
  pro Member. Member-Erkennung in `try_read_class_member`:
  Modifier-Loop (`static`/`async`/`readonly`/`public`/...,
  `get`/`set` mit Lookahead-Check), optional Generator-`*`, Name,
  optional `?`/`!`-Suffix, optional Generics, dann Entscheidung
  Method-vs-Property anhand `(`. Methoden bekommen
  `kind: "method"`/`"constructor"` (`name === "constructor"`)/
  `"getter"`/`"setter"`. Properties bekommen `kind: "property"` mit
  `type` und `default` als Source-Strings.

- **Interface/Enum**: Beide werden als Top-Level-Symbole erkannt;
  V1-Pragma: Body wird via `read_balanced("{","}")` geschluckt, der
  Symbol-Knoten enthaelt `name`, `extends` (nur fuer Interface),
  `spec`, leere `members[]`. Member-Detail ist in `## Out of scope`
  der M009 nicht eingeplant gewesen — kann eigene spaetere
  Erweiterung werden.

- **Type-Alias**: `type Name [<generics>] = <rhs>`. RHS wird via
  `read_expression_until_stop(stop_chars=[";"], respect_newline=true)`
  gelesen. Wenn nach `type` kein Identifier folgt, kein Type-Alias
  (Walker faellt zurueck und behandelt `type` als gewoehnlichen
  Identifier).

- **Edge-Cases**:
  - Strings, Template-Literals, Regex-Literals und Block-Kommentare
    werden vom Tokenizer als eigene Token-Klassen erkannt — der
    Marker-Check `is_spec_start_token` greift ausschliesslich auf
    `comment_line`-Token, sodass `// @spec` in `"..."`/`` `...` ``/
    `/.../`/`/* ... */`/`/** ... */` kein Spec-Trigger ist
    (Smoketest `ts_strings.ts` belegt das).
  - Optional-Chaining `?.` ist Two-Char-Op, Non-Null-Assertion `!`
    ist Single-Char-Punctuation, beide laufen folgenlos durch.
  - Qualifizierte Decorator-Namen `@Foo.Bar(...)`:
    `read_decorator` haengt `.<ident>` an `name` an, ohne
    Whitespace (Smoketest `ts_decorator_no_args.ts` belegt das).
  - Property-Type `User | null` mit `|`-Operator wird im Type-
    Source-String mit Spaces erhalten.

- **Existence-Check + Dangling**: Bei nicht existierender Datei
  Schema mit `warnings: ["file not found: $path"]`. Pending Spec
  ohne folgendes Symbol -> `warnings: ["dangling spec at line N"]`
  (gilt sowohl auf Top-Level als auch in Klassen-Bodys).

### Verifikations-Belege

- `php -l app/_share/spec_parser/ts_parser.php` -> "No syntax errors detected".

- **Smoketest `/tmp/ts_smoke.ts`** (Angular-Komponente): exit 0,
  JSON enthaelt
  - `file_spec: ["App entry component for /app-root."]` (1 Zeile, vor
    den `@Component`-Decoratoren)
  - `class AppComponent` mit
    `decorators: [{name:"Component", args_source:"{ selector: 'app-root', templateUrl: './app.component.html' }"}]`,
    `extends: []`, `implements: []`,
    `members[]` mit:
    - `property user` (type `"User | null"`, default `"null"`,
      spec `["current user, null when logged out"]`)
    - `method login` (signature
      `"async login(username: string, password: string): Promise<void>"`,
      decorators `[{name:"LogCall", args_source:""}]`,
      spec `["Logs in via auth service.","throws AuthError on bad credentials"]`)
  - `interface AuthService` mit `spec: ["Auth service contract."]`
  - `warnings: ["dangling spec at line 30"]` (1 dangling am Datei-Ende)

- **Smoketest `/tmp/ts_strings.ts`** (Strings/Regex/Template-Literals):
  exit 0, drei `const`-Eintraege (`s`/`r`/`t`), `warnings: []`,
  KEINE fake-Specs.

- **Smoketest `/tmp/ts_decorator_no_args.ts`** (Decorator-3-Faelle):
  exit 0, drei Klassen `A`/`B`/`C`:
  - A: `decorators:[{name:"Component", args_source:null}]`
  - B: `decorators:[{name:"Component", args_source:""}]`
  - C: `decorators:[{name:"Foo.Bar", args_source:"{x: 1}"}]` (qualifiziert)

- **Smoketest `/tmp/ts_dangling.ts`** (Function + dangling Spec):
  exit 0, function `ok` erkannt, `warnings: ["dangling spec at line 2"]`.

- **Bestehende Suite**: `php app/_share/spec_parser/tests/run.php` ->
  `27/27 passed` (php: 6/6, js: 4/4, nim: 7/7, lua: 8/8). Keine
  Regression.

- **Streu-Files**: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).

- **Temp-Cleanup**: `/tmp/ts_smoke.ts`, `/tmp/ts_strings.ts`,
  `/tmp/ts_decorator_no_args.ts`, `/tmp/ts_dangling.ts` nach den
  Smoketests entfernt.

### Tokenizer-Entscheidungen (Detail)

- **Generic-vs-Less-Than-Heuristik**: Im Tokenizer keine
  Disambiguierung — `<` ist immer Punctuation. Im Walker wird
  `read_balanced_angles` nur an genau definierten Deklarations-
  Positionen aufgerufen (nach Klassen-/Interface-/Funktions-/Type-/
  Member-Namen, jeweils unmittelbar bevor `(` oder `{` oder `=`
  folgen darf). Damit gibt es im praktischen Subset, den V1
  abdecken muss, keinen Generic-vs-Less-Than-Konflikt: an diesen
  Positionen kann `<` nur Generic sein, nicht Vergleich. In
  Body-Code (Methoden-Body, Default-Expressions) wird `<` weder
  als Generic noch als Vergleich semantisch behandelt — es wandert
  als Source-String durch (z.B. in `default` einer Property),
  reicht fuer die Spec-Extraktion.

- **`args_source`-Implementierung**: `read_decorator` ruft bei `(`
  unmittelbar `read_balanced("(",")")` auf. Das Ergebnis enthaelt
  immer die aeusseren Klammern — wir schneiden sie via
  `substr($balanced, 1, strlen($balanced) - 2)` ab und
  `trim()`-en, damit `@LogCall()` -> `""` (nach trim) und
  `@Component({...})` -> `"{ selector: ..., templateUrl: ... }"`
  (Whitespace im Inhalt zu Single-Spaces komprimiert wegen
  `read_balanced`). `null` wird gesetzt wenn nach dem Decorator-
  Namen kein `(` folgt.

- **Type-Annotation-Verschlucken**: Property-Branch in
  `try_read_class_member` und Variable-Branch in `read_var_decl`
  verwenden `read_expression_until_stop` mit Stop-Chars je nach
  Kontext (`["=",";","}",","]` fuer Property, `["=",";"]` fuer
  Variable). Whitespace im Type-String wird komprimiert,
  geschachtelte `(...)`/`[...]`/`{...}` werden korrekt mitgezaehlt
  (z.B. `Array<{a: number}>` als Type laeuft sauber durch wegen
  `angle_depth`-Tracker), Generic-Tiefen mit `>>`-Decompose-
  Heuristik. Return-Type ist ein eigener Pfad
  (`read_optional_return_type`), wird in die Signature angehaengt.

### Offene Fragen

- Interface- und Enum-Bodies werden in V1 nicht in `members[]`
  zerlegt — wenn Renderer das brauchen, eigene spaetere Erweiterung
  (z.B. M010 oder Folge-Ticket im selben Milestone).
- Parameter-Decorators (`@Inject(TOKEN) param: T`) landen im
  Signatur-Source-String, werden nicht extrahiert (siehe README:
  "V1 erkennt nur Symbol-Level-Decorators").
- Mehrere Top-Level-Variablen-Deklarationen mit Komma
  (`const a = 1, b = 2;`) werden V1-pragmatisch nur fuer den
  ersten Identifier erkannt, analog `js_parser`. Falls noetig:
  spaeter auseinanderziehen.
