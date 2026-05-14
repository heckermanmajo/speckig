# 0002 — Groovy-Parser (PHP-seitiger Tokenizer mit Annotation-Support)

Blocked by: 0001

## Done when

- `app/_share/spec_parser/groovy_parser.php` implementiert `groovy_parser::parse()` nach Schema aus M005/0001 + Annotation-Erweiterung aus 0001.
- PHP-seitiger State-Machine-Tokenizer. Strings (`'...'`, `"..."`, `'''...'''`, `"""..."""`), GStrings mit `$var`/`${...}`-Interpolation, Block-Kommentare, Groovydoc-Comments duerfen nie als Spec missverstanden werden.
- Spec-Erkennung: `// @spec` ... `// @end-spec`.
- Symbol-Erkennung:
  - `class`/`interface`/`enum`/`trait` Top-Level.
  - Klassen-Member: Felder (mit oder ohne Typ — Groovy hat optional typing), Methoden, Konstruktoren, statische Methoden, Groovy-Properties (Felder ohne expliziten Modifier sind Properties mit auto-getter/setter — wir erkennen sie als `kind: "property"`).
  - Top-Level Skript-Variablen / -Methoden (Groovy-Skripte ohne Klassen-Wrapper).
  - Annotations: `@Foo`, `@Foo("bar")`, `@Foo(value="bar", another=1)` gehoeren zum naechsten Symbol; werden als `annotations: [{name, args_source}]` angehaengt.
- Signatur als Source-String inkl. Typen (wenn vorhanden), Default-Args, Closures als Parameter-Default sind moeglich (`def foo(Closure cb = { -> }) {...}`) — V1 nimmt das als Source-String mit, ohne Closure-Body zu analysieren.
- `kind`-Werte: `class`, `interface`, `enum`, `trait`, `method`, `constructor`, `field`, `property`, `script-var`, `script-method`, `local`.
- Existence-Check + Dangling-Spec-Warning.
- `php -l` sauber.

## Smoketest

- Mini-`.groovy`-Fixture mit Spring-Boot-RestController (Datei-Spec + Klassen-Spec + Annotations + Methoden mit `@GetMapping`-Annotation und Spec inkl. Conditions). Manuell parsen, Output-Snippet im Done.

## Out of scope

- Java-Parsing (eigener Folge-Milestone falls noetig).
- Closure-Body-Analyse.
- Fixture-Test-Runner — 0003.
- Bean-Resolution.

## Done

- **`app/_share/spec_parser/groovy_parser.php`** vom Stub auf einen
  vollwertigen State-Machine-Tokenizer + Walker erweitert. Architektur
  spiegelt `ts_parser.php` (gleiches Spec-Block-Modell, gleiche
  Annotation-/Decorator-Schema-Form, gleiche Walker-Pipeline mit
  `pending_spec`/`pending_annotations`). Kein Regex (Decision 0006),
  keine externen Libs, kein Subprozess.

- **Tokenizer-Kinds** (alle State-Machine, kein Regex):
  - `whitespace`, `newline` als Teil von whitespace
  - `comment_line` (`// ...`)
  - `comment_block` (`/* ... */` und Groovydoc `/** ... */`, mehrzeilig)
  - `string_single` (`'...'`, einzeilig, mit `\`-Escape)
  - `string_double` (`"..."` einzeilig, GString mit `$var`/`${expr}`-
    Interpolation; `${...}` zaehlt Klammern korrekt mit nested
    Strings/Comments)
  - `string_triple_single` (`'''...'''` mehrzeilig, KEINE Interpolation)
  - `string_triple_double` (`"""..."""` mehrzeilig, GString-Interpolation)
  - `number`, `identifier`, `punctuation`
  - **Slashy-String** (`/.../`): pragmatisch NICHT als eigenes Token
    erkannt — alle `/` werden als Punctuation tokenisiert. Spec-Marker
    in Slashy-Strings koennten daher faelschlich erkannt werden.
    Slashy-Strings sind in Groovy-Codebases (ausserhalb Regex-Snippets)
    selten; Doku im Datei-Header.

- **Spec-Block-Erkennung** ueber `is_spec_start_token` /
  `is_spec_end_token` — exakt wie TS, nur an `comment_line`-Tokens.
  Inline-Spec-Inhalt nach `// @spec ` wird erste Spec-Zeile.
  Groovydoc `/** ... */` wird NICHT als Spec interpretiert (separater
  Token-Kind `comment_block`).

- **Annotation-Erkennung** (`read_annotation`): `@` + Identifier
  (qualifizierte Pfade `a.b.c` erlaubt) + optional `(args)`. Drei
  `args_source`-Faelle:
  - Ohne Klammern: `args_source: null`
  - Leere Klammern: `args_source: ""`
  - Mit Inhalt: `args_source: "<source-string>"` (innen, getrimmt)

- **Symbole** (mit korrekten `kind`-Werten aus M010/0001):
  - Top-Level: `class`, `interface`, `enum`, `trait` ueber
    `read_class_like` mit gemeinsamem Dispatch (kind_label).
    `extends`/`implements` als String-Arrays, mit Generics
    durchgereicht (`Comparable<Hello>`).
  - Klassen-Member: `method`, `constructor` (Name == Klassen-Name,
    kein Return-Type), `field` (mit explizitem Modifier), `property`
    (ohne Modifier — Groovy-Property mit Auto-Getter/Setter),
    `local` (Spec-Block in Methoden-Body).
  - Top-Level Skript: `script-method` (def name(...) oder Type
    name(...)), `script-var` (def name = ... oder Type name = ...).

- **`field` vs. `property`-Heuristik**: Member ist `field` wenn er
  einen Modifier traegt (`public`/`protected`/`private`/`static`/
  `final`/`abstract`/`synchronized`/`native`/`transient`/`volatile`/
  `strictfp`/`def`); sonst `property`. Begruendung im README dokumentiert
  (Groovy generiert Getter/Setter automatisch fuer Felder ohne
  Modifier). `def` zaehlt als expliziter Modifier — `def x = 1` an
  Klassen-Ebene wird als `field` gefuehrt; das matcht die README-
  Doku, die "Felder ohne expliziten Modifier sind Groovy-Properties"
  schreibt.

- **Datei-Header-Spec**: `first_decl_index` zaehlt `class`/`interface`/
  `enum`/`trait`/`def`/`@<annotation>` als Decl-Marker. `package` und
  `import` werden NICHT als Marker gezaehlt (entspricht der Smoketest-
  Erwartung "spec nach package/import zaehlt noch als file_spec").
  Klammer-Tiefe wird mitgefuehrt, damit `@`-Zeichen innerhalb von
  String-Defaults (selten) nicht als Decl-Marker triggert.

- **"Direkt darauffolgend"-Regel**: Walker akzeptiert zwischen
  Spec-Block und Symbol Whitespace, Block-/Groovydoc-Kommentare,
  beliebig viele Annotations und Modifier-Keywords (siehe
  `try_read_class_member`, `walk_tokens`). Konsistenz mit M010/0001-
  Spezifikation und TS-Walker-Verhalten.

- **Closure-Bodies** `{ -> ... }` werden brace-balanced
  durchgelaufen — V1 geht NICHT semantisch hinein. Spec-Bloecke in
  Methoden-Bodies werden als `kind: "local"` gesammelt (analog TS).

- **GString-Interpolation** (`scan_gstring_body`): `$var` und
  `${expr}` werden korrekt durchgelaufen. `${...}` zaehlt Klammern
  und durchlaeuft nested Strings (single/double/triple-single/
  triple-double), Line-Comments und Block-Comments korrekt, sodass
  `${"x"}`, `${foo("bar")}`, `${// inline}` den Tokenizer nicht
  abreissen.

- **Existence-Check + Dangling-Spec-Warning** wie ueblich (analog TS).

### Verifikations-Belege

- `php -l app/_share/spec_parser/groovy_parser.php` -> "No syntax errors detected".

- `php app/_share/spec_parser/index.php /tmp/groovy_smoke.groovy` -> exit 0:
  - `file_spec`: 1 Zeile ("REST controller for /api/foo endpoints.").
  - `symbols[0]`: `class FooController` mit
    `annotations: [{name:"RestController", args_source:null},
    {name:"RequestMapping", args_source:"\"/api\""}]`.
    `members[0]`: `kind: "property"`, name `service`,
    `annotations: [{name:"Autowired", args_source:null}]`,
    `spec: ["injected service"]`.
    `members[1]`: `kind: "method"`, name `listFoos`,
    `signature: "def listFoos()"`,
    `annotations: [{name:"GetMapping", args_source:"\"/foo\""}]`,
    2 Spec-Zeilen.
  - `warnings: ["dangling spec at line 26"]` — eine Warning fuer
    den dangling Spec-Block am Ende.

- `php app/_share/spec_parser/index.php /tmp/groovy_strings.groovy`:
  3 `script-var`-Symbole (`s`/`t`/`g`), `warnings: []`. Spec-Marker
  in `"..."`, `"""..."""` und GString werden NICHT als Spec erkannt.

- `php app/_share/spec_parser/index.php /tmp/groovy_annotation_no_args.groovy`:
  - A: `args_source: null` (kein `()`)
  - B: `args_source: ""` (leeres `()`)
  - C: `args_source: "\"/path\""` (mit Inhalt)
  Alle drei Faelle korrekt.

- `php app/_share/spec_parser/index.php /tmp/groovy_dangling.groovy`:
  `class Foo` + `warnings: ["dangling spec at line 2"]`.

- `php app/_share/spec_parser/tests/run.php` -> `38/38 passed`,
  exit 0 (php: 6/6, js: 4/4, nim: 7/7, lua: 8/8, ts: 11/11). Keine
  Regression in den anderen Sprach-Parsern.

- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).

- Temp-Files (`/tmp/groovy_*.groovy`) nach Smoketest aufgeraeumt.

### Tokenizer-Entscheidungen

- **Slashy-Strings**: nicht erkannt (V1). Alle `/` als Punctuation.
  Begruendung: kontextabhaengige Disambiguierung wie bei JS/TS-
  Regex-Literalen ist moeglich, aber die Slashy-String-Form ist
  in typischen Groovy-Codebases (Spring-Boot-Komponenten, Skripten)
  selten. Falsche-Positiv-Risiko fuer Spec-Marker im Slashy-String
  ist akzeptabel und im Datei-Header dokumentiert. Falls noetig:
  spaetere Erweiterung mit JS-Stil-Kontext-Heuristik.

- **GString-Interpolation**: `${...}` mit Klammer-Counter, durchlaeuft
  nested Strings (alle vier Quote-Formen), Line- und Block-Comments
  korrekt. `$ident` als Simple-Var-Form mit Identifier-Scan inkl.
  `.`-Zugriff (`$obj.field`).

- **Closure-Brace-Counting**: brace-balanced ohne Semantik —
  `{ -> ... }` wird wie jede andere geschweifte Klammer behandelt,
  weder als Funktions-Default noch als eigenes Konstrukt. Lokale
  Spec-Bloecke werden trotzdem (depth-agnostic) im Methoden-Body
  gefunden, weil `read_function_body` sequentiell durch alle Tokens
  geht und beim ersten `is_spec_start_token` einen Block einsammelt.

- **`field` vs. `property`**: Diskriminator ist die Praesenz mind.
  EINES Modifier-Keywords (inkl. `def`). Entscheidung im README
  dokumentiert; konsistent mit der Groovy-Property-Semantik (Felder
  ohne Modifier bekommen Auto-Getter/Setter).

- **Konstruktor-Erkennung**: Methode mit Name == Klassen-Name UND
  ohne Return-Type. `class_name` wird durch `read_class_body`
  durchgereicht. Funktioniert in beiden Schreibweisen (`Hello(...)
  {...}` ohne Return-Type, vs. `Hello hello(...)` waere kein Ctor
  weil Return-Type da ist).

- **`package`/`import` aus first_decl_index ausgeschlossen**: damit
  ein Spec-Block nach den Package/Import-Statements aber vor dem
  ersten echten Symbol noch als file_spec klassifiziert wird (das
  ist der haeufigste Fall in Spring-Boot-Komponenten und entspricht
  der Smoketest-Erwartung).

### Edge-Cases die hart waren

- **first_decl_index mit Klammer-Tracking**: `@`-Zeichen innerhalb
  von String-Defaults sind selten, aber theoretisch koennte ein
  String-Token-Inhalt mit `@` versehentlich als Decl-Marker
  durchschlagen. Loesung: `first_decl_index` zaehlt nur Top-Level-
  `@`-Punctuation (depth == 0). String-Tokens kommen aus dem
  Tokenizer als ein einziger Token (kein Decompose), darum kann
  ein `@` im String-Inhalt gar nicht als separates Token erscheinen
  — die Klammer-Tiefe-Pruefung ist trotzdem als Verteidigung in der
  Tiefe drin.

- **`def` als Modifier vs. Top-Level-Skript-Keyword**: in
  `try_read_class_member` zaehlt `def` als Modifier. In
  `try_read_script_decl` startet `def` einen Skript-Method/Var.
  Trennung sauber: top-level-Walker ruft `try_read_script_decl`
  nur ausserhalb von Klassen-Bodies; im Klassen-Body greift
  `try_read_class_member`.

- **`read_type_or_name`-Heuristik**: ein einzelner Identifier kann
  Type ODER Name sein. Loesung: liest erst eine "Type-or-Name"-
  Sequenz (Identifier mit optionalen `.path`, `<generics>`, `[]`-
  Suffixen), dann peekt das naechste Token: ist es wieder ein
  Identifier, war der erste der Type (zweiter ist Name); sonst war
  der erste der Name. `is_simple` blockiert die Name-Interpretation
  bei `Foo<T>`-artigen Lauf-Sequenzen, die nur als Type sinnvoll
  sind.

### Offene Fragen

- Enum-Konstanten (`enum Color { RED, GREEN, BLUE }`) werden in V1
  als `kind: "property"` erkannt (bare Identifier ohne Modifier im
  Klassen-Body). Schema-Doku hat dafuer keinen eigenen `kind`, also
  pragmatisch als `property` belassen. Falls die UI das anders
  rendern will, koennte man spaeter einen `kind: "enum-constant"`
  einfuehren — out of scope hier.

- Aenderung an `first_decl_index` (package/import ausgeschlossen)
  weicht in Detail vom TS-Parser ab (der zaehlt `import`/`export`
  als Decl-Marker). Begruendung: Groovy-`package`/`import` sind
  Datei-Metadaten, keine semantischen Symbole; in TypeScript ist
  `import` eine Symbol-bindende Form und gehoert ins Decl-Modell.
  Der Unterschied ist sprachgetreu, kein Bug.
