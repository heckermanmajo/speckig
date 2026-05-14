# 0003 — JS-Parser

See: pm/ideas/spec-as-comment.md, pm/decisions/0006-spec-parser.md
Blocked by: 0001

## Done when

- `app/_share/spec_parser/js_parser.php` (oder `.js`, je nach Architektur-Entscheidung in 0001) implementiert die Schnittstelle aus 0001 fuer JS-Dateien.
- Parser nutzt einen echten JS-Tokenizer/AST — entweder eine vendored Lib (Acorn / Esprima unter `app/_share/vendor/js/`, falls JS ausgefuehrt wird) oder einen PHP-seitigen JS-Token-Walker. Entscheidung folgt aus 0001.
- **Kein Regex** — gilt auch hier.
- Parser erkennt:
  - Datei-Header-Spec.
  - `function`/`class`/`const`/`let`/`var` Top-Level-Deklarationen mit Spec-Block direkt davor.
  - Klassen-Methoden und -Felder mit Spec-Block direkt davor.
  - Lokale Spec-Bloecke innerhalb einer Funktion (`kind: local`).
- Parser ignoriert vendored Code: `app/_share/vendor/js/` ist Pfad-Blacklist.
- Smoketest: eine Mini-`fixture.js`-Datei mit Datei-Spec, einer Funktion + Spec, einer const + Spec wird korrekt gerendert.
- **Tests fuer dieses Ticket:** Fixture-Datei manuell parsen. Echte Fixture-Tests in 0004.

## Aus der Idea wichtig

- Strings (single, double, template literals, regex literals) duerfen nie als Spec-Marker missverstanden werden. Vor allem Regex-Literals `/.../` sind tueckisch, weil `//` darin vorkommen kann — der Tokenizer muss Kontext kennen.
- BSD-Klammern (Decision 0004) sind in JS gegeben — kein Parser-Problem, nur erwaehnt.

## Done

- `app/_share/spec_parser/js_parser.php` ausimplementiert. Schnittstelle
  `js_parser::parse(string $path): array` aus 0001 unveraendert.
- Implementierung folgt der M005/0001-Festlegung: PHP-seitiger
  State-Machine-Tokenizer, kein Regex (Decision 0006). Tokenizer und
  Walker sind in zwei Phasen getrennt (siehe `tokenize()` und
  `walk_tokens()`).

### Tokenizer

- Token-Klassen: `whitespace`, `comment_line`, `comment_block`,
  `string_single`, `string_double`, `template_literal`, `regex_literal`,
  `number`, `identifier`, `punctuation`. Jedes Token = `[kind, text, line]`.
- Strings (`"..."`, `'...'`): `\`-Escape; unterminated bei Newline ->
  String wird abgeschlossen, Tokenizer crasht nicht.
- Template-Literals (`` `...` ``): `${...}`-Interpolation wird
  zeichenweise durchgekaut, wobei nested Strings / Templates / Comments /
  Klammern in der Interpolation behandelt werden, damit
  `${"..."}`, ``${`x`}``, `${// inline}` und verschachtelte `{}` den
  Tokenizer nicht brechen. Inhalt der Interpolation wird **nicht** als
  Code re-tokenisiert (V1-Pragma; Spec-Marker innerhalb einer
  Interpolation werden also auch nicht erkannt — akzeptabel).
- Regex-Literals: kontextabhaengig erkannt. Heuristik in
  `is_regex_context()`: das letzte signifikante Token (nicht
  whitespace/comment) entscheidet.
  - Value-produzierend (`number`, `string_*`, `template_literal`,
    `regex_literal`, Identifier-die-keine-Keywords-sind, `)`, `]`,
    Postfix `++`/`--`) -> Division (`/` als punctuation).
  - Expression-startend (Operatoren, `(`, `,`, `;`, `=`, `:`, `?`, `!`,
    `&&`, `||`, etc., sowie die Keywords `return`, `typeof`, `in`,
    `of`, `instanceof`, `new`, `delete`, `void`, `throw`, `yield`,
    `await`, `case`, `do`, `else`) -> Regex.
  - `}` -> pragmatisch Regex (Block-Ende ist der haeufigere Fall in
    realem Code; Object-Literal-Division `{a:1}/2` ist selten).
  - Datei-Anfang -> Regex.
  - Inhalt zwischen `/` und `/`: behandelt `[...]`-Char-Class
    (`/` darin ist literal), `\/`-Escape, und unterminated/Newline
    ohne Crash.

### Walker

- Top-Level-Walker (`walk_tokens`) trackt Brace/Paren-Tiefe (`depth`),
  damit nur `function`/`class`/`const`/`let`/`var` auf Tiefe 0 als
  Top-Level-Symbole erkannt werden. Wichtig fuer IIFE-Code wie
  `app/_share/js/content_loader.js`: dort ist alles in
  `(function () { ... })()` eingewickelt; ohne Depth-Tracking wuerde
  jede innere Funktion als Top-Level-Symbol auftauchen.
- Modifier-Keywords `export`, `async`, `default` werden vom Walker
  weggeschluckt (gehoeren zur folgenden Deklaration). Sie tauchen
  daher nicht im rekonstruierten Signatur-String von Top-Level-
  Funktionen auf — V1-Pragma. Klassen-Member-Modifier `static`,
  `async`, `get`, `set` sowie Generator-`*` werden **doch** in die
  Signatur uebernommen (siehe `try_read_class_member`).
- Datei-Header-Spec: erster Spec-Block, dessen `start_index` vor dem
  ersten Top-Level-Deklarations-Keyword (`function`, `class`, `const`,
  `let`, `var`, `import`, `export`) liegt, wandert in `file_spec`.
- Klassen-Member: `try_read_class_member` sammelt zuerst alle
  Modifier (`static`, `async`, `get`, `set`), dann optional Generator
  `*`, dann Identifier-Name, dann unterscheidet `(` (-> method/getter/
  setter) vs. `=`/`;`/Newline (-> property/field). Private-Fields
  (`#name`) werden korrekt erkannt, weil `#` als Identifier-Start
  konfiguriert ist.
- Lokale Spec-Bloecke in Funktions-Bodies werden als `members[]` mit
  `kind: "local"`, `name` = ersten 30 Zeichen der ersten Spec-Zeile.
- Dangling-Spec (Spec-Block ohne folgendes Symbol) -> Warning
  `"dangling spec at line N"`, Block verworfen. Erkannt sowohl auf
  Top-Level als auch innerhalb von Klassen-Bodies.
- Existence-Check: nicht existierende Datei liefert leeres Schema mit
  Warning `"file not found: $path"`, kein `error`-Feld
  (Vendor/Unsupported macht der Dispatcher).

### Edge-Cases die in Tests sichergestellt sind

- Spec-Marker in `string_double` / `string_single` / `template_literal` /
  `regex_literal` werden NIE als Marker erkannt — der Tokenizer trennt
  sie sauber von `comment_line`-Token.
- `regex.js`-Fixture: Regex mit `\/\/ @spec\n\/\/ @end-spec` im
  Pattern, String mit denselben Sequenzen, Template mit denselben —
  alle drei kommen als Defaults in `let r/s/t` an, keine fake-Specs,
  keine Warnings.
- `division.js`-Fixture: `let q = a / b;` und `c / d / e` werden als
  Division erkannt (vorheriges Token ist Identifier-Wert), nicht als
  Regex. `function foo` wird danach normal erkannt.
- `dangling.js`-Fixture: Spec-Block am Datei-Ende ohne folgendes
  Symbol -> Warning, `function ok` davor unberuehrt.
- `content_loader.js` (echte Datei, IIFE-only): liefert `file_spec: []`,
  `symbols: []`, `warnings: []`. Akzeptables V1-Verhalten laut Ticket.
- Generator-`*`, `static`, `async`, `get`, `set`-Member, private
  `#field`, `extends Foo.Bar`-qualifizierte Pfade: alle ad-hoc getestet
  (Fixture wieder weggeworfen, da nicht im Ticket-Pflicht-Test-Set).

### Smoketest-Belege

- `php -l app/_share/spec_parser/js_parser.php` -> "No syntax errors
  detected".
- `php app/_share/spec_parser/index.php /tmp/js_parser_smoke.js` ->
  exit 0, JSON:
  - `file_spec`: ["File-level: tiny demo for js parser smoketest."]
  - `symbols[0]`: function `add`, signature `function add(a, b)`,
    spec ["Adds two numbers."], members [].
  - `symbols[1]`: class `User`, extends [], spec ["User record."],
    members:
      - property `name`, default `"\"\""`, spec ["username field"]
      - method `greet`, signature `greet()`, spec ["Greets the user."],
        members []
  - `warnings`: ["dangling spec at line 31"]
- `php app/_share/spec_parser/index.php /tmp/js_parser_regex.js` ->
  exit 0, `symbols` enthaelt nur die drei `let`-Deklarationen
  (r/s/t), KEINE Funktionen/Klassen aus den Pattern-Inhalten.
  `warnings`: [].
- `php app/_share/spec_parser/index.php /tmp/js_parser_division.js` ->
  exit 0, `symbols`: `let q` (default `"a / b"`), `let r` (default
  `"c / d / e"`), `function foo` mit signature `function foo()`.
  `warnings`: [].
- `php app/_share/spec_parser/index.php /tmp/js_parser_dangling.js` ->
  exit 0, `symbols`: function `ok` mit signature `function ok()`.
  `warnings`: ["dangling spec at line 2"].
- `php app/_share/spec_parser/index.php app/_share/js/content_loader.js`
  -> exit 0, kein Crash. `file_spec: []`, `symbols: []`,
  `warnings: []`. Begruendung siehe oben (IIFE-Wrapper, Depth-Tracking).
- `php app/_share/spec_parser/index.php /tmp/does_not_exist.js` -> exit 0,
  Warning `"file not found: /tmp/does_not_exist.js"`, kein `error`-Feld.

### Streu-File-Cleanup

- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"`
  -> nur `./app.sqlite` (kanonisch).
- `/tmp/js_parser_*.js` (smoke / regex / division / dangling) bleiben
  fuer den Pflicht-Test stehen, werden aber nach Commit-Verifikation
  vom Subagent geloescht (siehe Cleanup-Bericht im Subagent-Output).
- Kein `php -S` gestartet — UI-Anbindung ist 0005.

### Offene Fragen / Followups

- `async` / `export`-Modifier landen nicht im Signatur-String von
  Top-Level-Funktionen, weil sie vom Walker weggeschluckt werden.
  Wenn das in M005/0004 (Fixture-Tests) oder M005/0005 (UI) stoert,
  bauen wir sie wieder rein — aktuell aber Pragma.
- Destructuring in `const { a, b } = ...` und Multi-Var
  `const a = 1, b = 2;` werden nicht ausgepackt — der erste
  Identifier-Name geht durch, Rest wird in `default` mitgeschleift.
  Out of scope fuer V1; wenn der Pilot das braucht, eigenes Ticket.
- Object-Literal-Division `{a:1}/2` wird als Regex misinterpretiert,
  weil `}` -> Regex-Kontext. Sehr selten in realem Code, akzeptables
  Trade-off; siehe Tokenizer-Begruendung oben.
- Spec-Marker innerhalb einer Template-Literal-Interpolation werden
  nicht erkannt (V1-Pragma: Interpolations-Inhalt wird nicht re-
  tokenisiert). Falls ein Spec-Block tatsaechlich mal in
  `${...}` landen sollte, kann der Tokenizer in M009 nachgeruestet
  werden.
