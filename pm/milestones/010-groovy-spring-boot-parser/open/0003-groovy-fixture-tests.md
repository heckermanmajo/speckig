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

(append after work)
