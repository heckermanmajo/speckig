# 0002 — PHP-Parser via token_get_all

See: pm/ideas/spec-as-comment.md, pm/decisions/0006-spec-parser.md
Blocked by: 0001

## Done when

- `app/_share/spec_parser/php_parser.php` implementiert die in 0001 festgelegte Schnittstelle.
- Parser nutzt ausschliesslich `token_get_all` (PHP-Built-in) — keine Regex, keine externen Libs.
- Parser erkennt:
  - Datei-Header-Spec (Block-Kommentar oben, vor `namespace` oder `class`).
  - `class`/`interface`/`trait` mit Spec-Block direkt davor.
  - Properties (`public string $foo = "..."`) mit Spec-Block direkt davor.
  - Methoden / `function`-Deklarationen mit Spec-Block direkt davor.
  - Konstanten (`const FOO = ...`) mit Spec-Block direkt davor.
  - Lokale Spec-Bloecke innerhalb einer Methode (gehen in `members[]` der Methode mit `kind: local`).
- Parser ignoriert Spec-Bloecke, denen kein Symbol folgt (Edge-Case: dangling Spec) — gibt aber eine Warnung im JSON-Output (`warnings: []`).
- Parser ignoriert vendored Code: Pfade unter `app/_share/vendor/` werden vor dem Parsen abgelehnt.
- Output enthaelt fuer jedes Symbol:
  - `kind`, `name`
  - bei Funktion/Methode: `signature` als String (Argumente mit Typen + Defaults, Returntyp).
  - bei Property/Konstante: `type`, `default` als Strings.
  - `spec`: Array von Strings, eine Zeile pro Spec-Inhalt-Zeile (ohne `// ` Praefix, ohne `@spec`/`@end-spec`).
- Aufruf gegen `app/user/data/User.php` (aus M004-Pilot) liefert sechs Felder mit jeweils einer Spec-Zeile.
- Aufruf gegen `app/user/actions/CreateUserAction.php` liefert die Klasse, das Feld `created_user_id`, und die Methode `execute` mit ihrem Spec-Block (Intent + Conditions als Zeilen).
- `php -l` sauber.
- **Tests fuer dieses Ticket:** mindestens die zwei Pilot-Dateien manuell parsen, Output zeigen. Echte Fixture-Tests landen in 0004.

## Aus der Idea wichtig

- Heredoc-/Nowdoc-/String-Inhalte duerfen nie als Spec-Marker missverstanden werden — `token_get_all` macht das per Default richtig, aber explizit testen.
- Spec-Block-Marker ist exakt `// @spec` ... `// @end-spec`. Token-Typ ist `T_COMMENT` (One-Line), nicht `T_DOC_COMMENT`. Bestehende `/** ... */` werden nicht als Spec interpretiert.

## Done

- `app/_share/spec_parser/php_parser.php` ausimplementiert. Schnittstelle
  `php_parser::parse(string $path): array` aus 0001 unveraendert.
- Ausschliesslich `token_get_all` genutzt — kein Regex auf Quelltext, auch
  nicht fuer Spec-Marker (Decision 0006). Eine String-Normalisierung der
  rekonstruierten Methoden-**Signatur** laeuft per `for`-Loop, nicht per
  Regex.
- Spec-Block-Erkennung deckt beide Stile ab, die der Pilot benutzt:
  - Mehrzeilig: `// @spec` + content-Zeilen + `// @end-spec`.
  - Kompakt: `// @spec <inline content>` + `// @end-spec` — der Inline-Teil
    nach `@spec` wird als erste Spec-Zeile uebernommen. Im README/0001 nicht
    explizit dokumentiert; der Pilot (`User.php`-Properties) verwendet ihn,
    und das Ziel-JSON aus dem README erwartet ihn — also implementiert.
- Datei-Spec-Heuristik wie spezifiziert: erster Spec-Block, dessen
  `start_line < first_T_NAMESPACE_line`, wandert in `file_spec`. Wenn die
  Datei kein `namespace` hat, ist jeder Spec-Block Symbol-Spec.
- Symbole erkannt: `class`, `interface`, `trait`, `function`, `method`,
  `property`, `const`, `local`. `extends` und `implements` als String-Arrays
  aus `T_EXTENDS`/`T_IMPLEMENTS`. Interfaces tragen kein `implements`-Feld
  (sondern nur `extends`).
- Methoden-/Funktions-Signatur als Source-String rekonstruiert inkl.
  Modifier (`static`, `final`, ...), Parameter mit Typen+Defaults, Returntyp.
  Whitespace zu einzelnen Spaces normalisiert; Spaces vor `(` `)` `,` `:`
  entfernt; nach `,` und `:` jeweils ein Space.
- Property-/Const-`type` und `default` als Source-Strings, nicht eval'd
  (`"\"\""`, `"0"`, `"new self()"`).
- Lokale Spec-Bloecke in Methoden-Bodies werden als `members[]` mit
  `kind: "local"` gesammelt; `name` ist die ersten 30 Zeichen der ersten
  Spec-Zeile.
- Dangling-Spec (Spec-Block ohne folgendes Symbol) -> Warning
  `"dangling spec at line N"`, Block verworfen. Erkannt sowohl auf
  Top-Level als auch innerhalb der Klasse.
- Existence-Check: nicht existierende oder nicht lesbare Datei liefert
  leeres Schema mit Warning `"file not found: $path"` — kein `error`-Feld
  (das macht der Dispatcher fuer Vendor/Unsupported).
- Edge-Cases bewusst behandelt:
  - **String-Interpolation `"...{$x->y}..."`** liefert `T_CURLY_OPEN` plus
    matching raw `}`. Ohne Spezialbehandlung wird das `}` als Klassen-
    Body-Ende falsch interpretiert (war im ersten Lauf Bug:
    `CreateUserAction::execute()` lies seinen Body in den Klassen-Walker
    bluten und erzeugte Phantom-Properties `new_user`, `response`).
    Fix: `T_CURLY_OPEN` und `T_DOLLAR_OPEN_CURLY_BRACES` (wenn verfuegbar)
    bumpen die Brace-Tiefe in `read_class_body`, `read_function_body` und
    `read_expression_until_semicolon`.
  - **PHP 8.4 readonly / asymmetric visibility**: `T_READONLY`,
    `T_PUBLIC_SET`, `T_PROTECTED_SET`, `T_PRIVATE_SET` sind in
    `is_modifier_type()` per `defined()`-Guard hinzugefuegt — kein Crash
    auf alten PHP-Versionen, korrekte Behandlung wenn vorhanden.
  - **Doc-Comments und String/Heredoc-Inhalte** mit `// @spec` darin
    werden vom Tokenizer als T_DOC_COMMENT bzw. T_ENCAPSED_AND_WHITESPACE
    geliefert, nicht als T_COMMENT — daher nie als Spec erkannt.
    Explizit getestet (siehe Smoketests unten).
  - **Fake-Marker** wie `// @spec-fake` oder `// @special-comment` werden
    nicht als Spec-Start erkannt, weil `is_spec_start_token` exakt `@spec`
    oder `@spec ` (mit Trennzeichen) verlangt.
  - **PHP 8.3+ typed Konstanten** (`const TYPE NAME = ...`) werden grob
    behandelt: das letzte T_STRING vor `=` wird als Name genommen, alles
    davor als Typ-Source-String. Kein typed Const im Pilot getestet, aber
    `const FOO = "bar"` (untyped) funktioniert.
- README im selben Commit nicht angepasst — der Parser liefert exakt das
  dokumentierte JSON fuer beide Pilot-Dateien, kein Drift.

## Smoketest-Belege

- `php -l app/_share/spec_parser/php_parser.php` -> "No syntax errors
  detected".
- `php app/_share/spec_parser/index.php app/user/data/User.php` -> exit 0.
  Output strukturell identisch zum README-Beispiel:
  - `file_spec`: ["Authenticated platform user; admin flag controls
    privileged endpoints."]
  - `symbols[0].kind == "class"`, `name == "User"`,
    `extends == ["DataClass"]`, `implements == []`.
  - 6 `members[]`-Eintraege, jeweils `kind: "property"`, korrekte `type`
    (`string`/`int`), `default` (`"\"\""`/`"0"`), eine `spec`-Zeile pro
    Property — alle Strings deckungsgleich mit dem README.
- `php app/_share/spec_parser/index.php app/user/actions/CreateUserAction.php`
  -> exit 0. Output:
  - `file_spec`: ["Admin-only action that validates inputs and inserts a
    new User row, exposing the created id."]
  - Klasse `CreateUserAction`, `extends == ["Action"]`, `implements == []`.
  - `members[]`: Property `created_user_id` (int, "0", spec
    "id of the user this action created; 0 before execute() ran"),
    Methode `__construct` (signature `"function __construct()"`, leere
    spec, leere members), Methode `execute` mit signature
    `"static function execute(string $username, string $password,
    string $email, string $is_admin = \"0\"): self"` und 11 Spec-Zeilen.
- **String/Heredoc-Test:** `/tmp/spec_parser_string_test.php` mit
  `// @spec ... // @end-spec` in einem `"..."`-String und einem
  Heredoc -> `symbols == []`, `warnings == []`. Keine fake-Specs erkannt,
  keine dangling Warnings.
- **Doc-Comment-Test:** `/tmp/spec_parser_doccomment_test.php` mit
  `/** ... // @spec ... // @end-spec ... */` -> `file_spec == []`,
  `symbols == [{ kind: "function", name: "regular_function", spec: [] }]`.
- **Dangling-Test:** `/tmp/spec_parser_dangling_test.php` mit Klasse +
  trailing `// @spec\n// orphan\n// @end-spec` -> `warnings: ["dangling
  spec at line 9"]`, Block verworfen.
- **Existence-Test:** `php app/_share/spec_parser/index.php
  /tmp/does_not_exist.php` -> exit 0,
  `warnings: ["file not found: /tmp/does_not_exist.php"]`, kein
  `error`-Feld.
- **Local-Spec-Test:** `/tmp/spec_parser_local_test.php` mit Methode, die
  einen `// @spec ... // @end-spec`-Block im Body hat -> Methode bekommt
  `members: [{ kind: "local", name: "<first 30 chars>", spec: [...] }]`.
- **Interface-Test:** Interface mit `extends Iterator, Countable`,
  Const, abstract Methode -> alle korrekt erkannt; `implements`-Feld
  weggelassen (interfaces tragen nur `extends`).
- **Vendor-Test:** `php app/_share/spec_parser/index.php
  app/_share/vendor/Parsedown.php` -> exit 2, `error: "vendor code not
  parsed"` (vom Dispatcher, nicht vom php_parser).

## Streu-File-Cleanup

- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"`
  -> nur `./app.sqlite` (kanonisch).
- `/tmp/spec_parser_*` aufgeraeumt, keine Reste.
- Kein `php -S` gestartet — UI-Anbindung ist 0005.
