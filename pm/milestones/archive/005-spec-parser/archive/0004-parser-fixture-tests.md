# 0004 — Parser-Fixture-Tests (PHP + JS)

See: pm/how-to/testing.md, pm/ideas/spec-as-comment.md
Blocked by: 0002, 0003

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/` enthaelt Fixture-Paare:
  - `php/<name>.php` + `php/<name>.expected.json`
  - `js/<name>.js` + `js/<name>.expected.json`
- Mindestens diese Fixtures sind drin (PHP):
  - `class_with_fields.php` — entspricht Pilot-`User.php`, sechs Felder, Datei- und Klassen-Spec.
  - `function_with_conditions.php` — entspricht Pilot-`CreateUserAction.php::execute`, Spec mit Intent + Conditions-Zeilen.
  - `dangling_spec.php` — Spec-Block ohne folgendes Symbol -> `warnings[]` enthaelt Eintrag.
  - `spec_in_string.php` — Heredoc und Strings, die `// @spec` als Text enthalten -> werden NICHT als Spec geparst.
  - `vendor_blacklist.php` — Datei unter `app/_share/vendor/` -> Parser lehnt ab.
  - `no_spec.php` — Datei ohne jede Spec -> `file_spec: []`, `symbols[]` ohne `spec`-Eintraege.
  - `local_spec.php` — Spec innerhalb einer Methode -> erscheint in `members[]` mit `kind: local`.
- Mindestens diese Fixtures sind drin (JS):
  - `function_with_spec.js`, `class_with_spec.js`, `regex_literal.js` (Regex-Literal mit `//` darf nicht als Spec interpretiert werden), `no_spec.js`.
- Test-Runner: ein simples `app/_share/spec_parser/tests/run.php`, das alle Fixtures durchgeht, Parser ruft, JSON-Output mit `.expected.json` per Deep-Compare vergleicht (kein Regex; `json_decode` + rekursiver Array-Vergleich) und am Ende eine Zusammenfassung ausgibt: `X/Y passed`.
- Aufruf `php app/_share/spec_parser/tests/run.php` exit 0 wenn alle gruen, exit 1 sonst.
- Smoketest im Ticket: Output mit Pass-Counter zeigen.

## Aus der Idea wichtig

- Fixtures sind die Wahrheits-Tests. Wenn eine Fixture fehlt, fehlt Test-Coverage — keine Unit-Tests als Ersatz.
- Bei Aenderungen am Schema (0001) muessen alle `.expected.json` mit aktualisiert werden — das ist explizit gewollt, weil es das Schema diszipliniert haelt.

## Done

- Verzeichnis `app/_share/spec_parser/tests/` angelegt mit:
  - `run.php` (CLI-Test-Runner, `@spec`-Bloecke an Top-Level + Helpers).
  - `fixtures/php/` (sieben Source/Expected-Paare).
  - `fixtures/js/` (vier Source/Expected-Paare).

### Fixtures (PHP)

- `class_with_fields.php` / `.expected.json` — Pilot-aehnliche Klasse `Product`
  mit Datei-Spec, Klassen-Spec und drei typed properties (`string`/`int`/`bool`),
  jede mit eigener Spec-Zeile.
- `function_with_conditions.php` / `.expected.json` — Top-Level-Funktion
  `validate_user_input(string, string, int = 64): string` mit Spec aus Intent
  + 4 Conditions (3 `throws ...`, 1 `returned ... is`).
- `dangling_spec.php` / `.expected.json` — `class Foo {}` plus Spec-Block
  am Datei-Ende ohne folgendes Symbol. Erwartet:
  `symbols[]` enthaelt `Foo` mit `spec: []`, `warnings[]` enthaelt
  `"dangling spec at line 9"`.
- `spec_in_string.php` / `.expected.json` — `// @spec`-Marker innerhalb
  Single-Quote-, Double-Quote-, Heredoc- und Nowdoc-Literalen. Erwartet:
  `symbols: []`, `warnings: []` (Tokenizer trennt Strings sauber von
  T_COMMENT — Marker werden nicht erkannt).
- `no_spec.php` / `.expected.json` — Klasse `Bare` (1 Property) plus
  `bare_helper(int): int`, beide ohne `@spec`. Erwartet:
  `file_spec: []`, beide Symbole mit `spec: []`, kein Warning.
- `local_spec.php` / `.expected.json` — Klasse `Calculator` mit Methode
  `compute(int, int): int`, deren Body einen lokalen
  `// @spec ... // @end-spec`-Block enthaelt. Erwartet:
  Methode hat `members[]` mit einem `kind: "local"`-Eintrag.

`vendor_blacklist.php` wurde **nicht** als Fixture angelegt; statt dessen
laeuft im Runner ein synthetischer Test
(`spec_parser::parse("app/_share/vendor/anything.php")`), der das erwartete
`error: "vendor code not parsed"` direkt assertet — sauberer als eine
Fixture unter `app/_share/vendor/` (siehe Empfehlung im Ticket-Body,
Option b).

### Fixtures (JS)

- `function_with_spec.js` / `.expected.json` — Datei-Spec + eine
  `function build_page_title(raw_path, fallback)` mit 3-zeiliger Spec.
- `class_with_spec.js` / `.expected.json` — Datei-Spec + `class TreeNode
  extends Widget` mit Klassen-Spec, zwei Properties (`label`, `child_count`)
  je mit Spec, und einer Methode `render(prefix)` mit Spec.
- `regex_literal.js` / `.expected.json` — Regex-Literal `/\/\/ @spec...\/\/
  @end-spec/`, plus Single-Quote-, Double-Quote- und Template-Literal mit
  `// @spec`-Text, plus eine echte `function detect_marker(input)` mit
  echter Spec. Erwartet: nur `detect_marker` traegt eine Spec; die vier
  `let`-Deklarationen davor stehen mit `spec: []` im Output (der
  PHP-State-Machine-Tokenizer erkennt sie als Top-Level-Variablen
  korrekt). `warnings: []`.
- `no_spec.js` / `.expected.json` — `function bare_helper` + `class
  BareWidget` ohne Marker. Erwartet wie PHP no_spec.

### Test-Runner

- `app/_share/spec_parser/tests/run.php` ist CLI-only
  (`PHP_SAPI === "cli"`-Check, sonst exit 1).
- Iteriert via `glob()` ueber `fixtures/php/*.php` und `fixtures/js/*.js`,
  ruft `spec_parser::parse($repo_relative_path)`, parst die zugehoerige
  `.expected.json` mit `json_decode(..., true)` und vergleicht per
  rekursivem Deep-Compare (kein Regex — Decision 0006).
- Fixtures werden mit dem Repo-relativen Pfad
  (`app/_share/spec_parser/tests/fixtures/...`) aufgerufen, damit das
  `file`-Feld im Parser-Output Maschinen-unabhaengig bleibt.
- Diff-Ausgabe: bei FAIL werden bis zu drei Pfad-Differenzen gezeigt
  (z.B. `symbols[0].name: expected "Foo", got "foo"`); Strings werden
  bei > 80 Zeichen abgeschnitten, damit Diff-Meldungen lesbar bleiben.
- Synthetische Tests am Ende:
  - Vendor-Blacklist: `spec_parser::parse("app/_share/vendor/anything.php")`
    -> `error: "vendor code not parsed"`.
  - Unsupported-Language: `spec_parser::parse("foo.css")`
    -> `error: "unsupported language", extension: "css"`.
- Zusammenfassung: `X/Y passed`. Exit 0 wenn alle gruen, Exit 1 sonst.
  Bei FAIL wird zusaetzlich eine Liste der gescheiterten Fixtures
  ausgegeben.

### Smoketest-Output

```
$ php app/_share/spec_parser/tests/run.php
spec_parser fixture tests
=========================
PASS app/_share/spec_parser/tests/fixtures/php/class_with_fields.php
PASS app/_share/spec_parser/tests/fixtures/php/dangling_spec.php
PASS app/_share/spec_parser/tests/fixtures/php/function_with_conditions.php
PASS app/_share/spec_parser/tests/fixtures/php/local_spec.php
PASS app/_share/spec_parser/tests/fixtures/php/no_spec.php
PASS app/_share/spec_parser/tests/fixtures/php/spec_in_string.php
PASS app/_share/spec_parser/tests/fixtures/js/class_with_spec.js
PASS app/_share/spec_parser/tests/fixtures/js/function_with_spec.js
PASS app/_share/spec_parser/tests/fixtures/js/no_spec.js
PASS app/_share/spec_parser/tests/fixtures/js/regex_literal.js

synthetic tests
---------------
PASS vendor blacklist rejects app/_share/vendor/* paths
PASS unsupported language rejects .css

12/12 passed
$ echo $?
0
```

Failure-Pfad ebenfalls verifiziert: per temporaerer Manipulation einer
`expected.json` (Klassennamen geaendert) zeigt der Runner einen
sauberen Diff (`symbols[0].name: expected "Bare2", got "Bare"`), gibt
`11/12 passed`, Failure-Liste, exit 1 zurueck. Aenderung war temporaer
und wurde wieder zurueckgenommen.

### Parser-Bugs

Keine. Alle Erwartungen entsprechen exakt dem Schema im README; alle
fixture-getriebenen Outputs des Parsers stimmen damit ueberein. Auch
Edge-Cases wie der Spec-Marker innerhalb von Heredoc/Nowdoc, Regex-
Literal mit `//`, und String-Default mit `\"\"` werden korrekt
gehandhabt.

### Schema-Erkenntnisse

- `kind: "let"` (und analog `const`/`var`) erscheint als Top-Level-
  Symbol-Kind im JS-Parser-Output. Ist im README in der Symbol-Tabelle
  enthalten (zusammen mit den anderen kinds), wird aber von keinem der
  archivierten 0001/0003-Beispiele explizit gezeigt — die
  `regex_literal`-Fixture deckt das implizit ab.
- `extends`/`implements` fehlen je nach Container-Kind: PHP-Parser
  liefert `extends` und `implements` fuer Klassen, nur `extends` fuer
  Interfaces/Traits (s. `read_class_like`); JS-Parser liefert nur
  `extends` fuer Klassen (kein `implements`-Konzept). Die expected.json
  spiegeln das exakt wider.
- `name` bei `kind: "local"` ist ein 30-Zeichen-Mehrzeichen-Praefix der
  ersten Spec-Zeile (`mb_substr($first_line, 0, 30)`). Die expected.json
  von `local_spec.php` reproduziert das genau.

### Cleanup

- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"`
  -> `./app.sqlite` (kanonischer Repo-Root, vorab existent).
- Keine Server gestartet, daher kein TaskStop.
- Keine Streufiles unter `tests/`; alle neuen Files leben innerhalb von
  `app/_share/spec_parser/tests/`.
