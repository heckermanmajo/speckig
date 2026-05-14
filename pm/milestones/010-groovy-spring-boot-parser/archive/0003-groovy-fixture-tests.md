# 0003 — Groovy-Fixture-Tests

Blocked by: 0002

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/groovy/` mit Fixture-Paaren `<name>.groovy` + `<name>.expected.json`.
- Mindestens diese Fixtures:
  - `spring_rest_controller.groovy` — `@RestController @RequestMapping("/api") class FooController { @GetMapping("/foo") ... }` mit Datei-, Klassen- und Methoden-Specs.
  - `spring_service.groovy` — `@Service class FooService { @Autowired BarRepo bar; ... }`.
  - `groovy_class_with_properties.groovy` — Klasse mit Property-Stil-Feldern (ohne Modifier), Specs an einzelnen Properties.
  - `gstring_interpolation.groovy` — String mit `${...}` und `// @spec`-Text drin -> NICHT als Spec geparst.
  - `triple_quoted_string.groovy` — `"""..."""` mit `// @spec`-Text -> NICHT geparst.
  - `closure_arg.groovy` — Methode mit Closure-Parameter, Closure-Body wird nicht analysiert aber Tokenizer ueberlebt.
  - `script_top_level.groovy` — Groovy-Skript ohne Klassen-Wrapper.
  - `dangling_spec.groovy`.
  - `local_spec.groovy`.
  - `no_spec.groovy`.
- Test-Runner integriert Groovy-Fixtures, Aufruf zeigt `groovy: X/Y passed`.
- `php -l` sauber.

## Out of scope

- Java-Fixtures.
- Andere Sprach-Fixtures.

## Done

- **Verzeichnis** `app/_share/spec_parser/tests/fixtures/groovy/` mit 10
  Fixture-Paaren `<name>.groovy` + `<name>.expected.json`. Eine Fixture
  pro Done-when-Bullet:
  - `spring_rest_controller.groovy` — `@RestController @RequestMapping("/api")`
    `class FooController` mit `@GetMapping("/foo") def listFoos()`. Datei-,
    Klassen- und Methoden-Spec; `annotations[]` mit allen drei
    `args_source`-Faellen demonstriert (`null`, `""` kommt in
    `dangling_spec`/`closure_arg` nicht vor, dafuer in `spring_service`
    via `@Service` ohne Klammern).
  - `spring_service.groovy` — `@Service class FooService { @Autowired BarRepo bar; def find(Long id) }`.
    `bar` wird vom Parser als `kind: "property"` gefuehrt (kein Modifier;
    `@Autowired` ist Annotation, nicht Modifier — entspricht der Regel
    aus M010/0002).
  - `groovy_class_with_properties.groovy` — `class Person` mit drei
    Property-Stil-Feldern (`String name`, `int age`, `String email`),
    Specs an einzelnen Properties.
  - `gstring_interpolation.groovy` — `"...${...}..."` und `"...$var..."`
    GStrings mit `// @spec`-Text drin: NICHT als Spec geparst. Drei
    Symbole (`script-var` × 2 + `script-method`).
  - `triple_quoted_string.groovy` — `"""..."""` und `'''...'''` mit
    `// @spec`-Text drin: NICHT als Spec geparst. Drei Symbole
    (`script-var` × 2 + `script-method`).
  - `closure_arg.groovy` — `void run(Closure cb = { -> println("default") })`
    plus `void run_twice(Closure cb)`. Closure-Default-Body wird vom
    Tokenizer durchgelaufen und landet als Bestandteil des
    `signature`-Source-Strings.
  - `script_top_level.groovy` — Groovy-Skript ohne Klassen-Wrapper,
    `script-var` × 2 (`def greeting`, `int counter`) + `script-method` × 2
    (`def greet`, `def repeat`).
  - `dangling_spec.groovy` — `class Foo` + dangling Spec-Block am Datei-
    Ende -> `warnings: ["dangling spec at line 12"]`.
  - `local_spec.groovy` — `class Compute` mit `int add(int a, int b)`,
    lokaler Spec-Block im Methoden-Body -> `members[]` mit
    `kind: "local"`.
  - `no_spec.groovy` — `class Bare` mit `private String label` (-> `field`),
    `String name` (-> `property`) und `String greet(String who)`
    (-> `method`). Keine Spec-Bloecke.

- **Test-Runner** `app/_share/spec_parser/tests/run.php` um Groovy-
  Fixtures erweitert: `glob("/groovy/*.groovy")` mit Sortierung,
  `groups[]`-Eintrag mit `lang: "groovy"`, `ext: ".groovy"`,
  `$preferred_order` um `"groovy"` ergaenzt. Spec-Bloecke der drei
  betroffenen Funktionen aktualisiert (Sprach-Liste durchgaengig).

- **`.expected.json`-Erstellung**: pro Fixture wurde der Parser einmal
  ausgefuehrt (`php index.php <fixture>`), das Ergebnis-JSON gegen das
  README-Schema (`## Symbol-Objekt` + `## Groovy`) gegengelesen und
  unveraendert als `<name>.expected.json` festgeschrieben. Keine
  Parser-Aenderungen noetig.

### Verifikations-Belege

- `php -l app/_share/spec_parser/tests/run.php` -> "No syntax errors detected".
- `php app/_share/spec_parser/tests/run.php` -> exit 0:
  - 10× PASS fuer `app/_share/spec_parser/tests/fixtures/groovy/*.groovy`.
  - Pro-Sprach-Zusammenfassung am Ende: `php: 6/6`, `js: 4/4`, `nim: 7/7`,
    `lua: 8/8`, `ts: 11/11`, `groovy: 10/10`.
  - Gesamt: `48/48 passed`.
  - Keine Regression in den anderen Sprach-Parsern.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*" -not -path
  "./.git/*"` -> nur `./app.sqlite` (kanonisch).
- `git status`: 10 `.groovy` + 10 `.expected.json` (groovy/-Verzeichnis
  neu) + `tests/run.php` modifiziert + Ticket-Move + milestone.md
  Box. Keine sonstigen Touches.

### Beobachtung zum Parser (kein Bug)

- `@Autowired BarRepo bar` in `spring_service.groovy` wird als
  `kind: "property"` gefuehrt. Das ist konsistent mit der M010/0002-Regel
  ("`field` vs. `property`-Diskriminator ist Praesenz mind. eines
  Modifier-Keywords"; `@Autowired` zaehlt als Annotation, nicht als
  Modifier). Die expected.json bildet das ab. Falls Spring-Boot-Autowired-
  Felder semantisch lieber als `field` modelliert werden sollen, waere
  das eine M010/0002-Folge-Aenderung — out of scope hier.

### Out-of-scope eingehalten

- Keine Java-Fixtures.
- Keine andere Sprache angefasst.
- `groovy_parser.php` unveraendert.
- `README.md` unveraendert.
- `app/file.php` unveraendert.
