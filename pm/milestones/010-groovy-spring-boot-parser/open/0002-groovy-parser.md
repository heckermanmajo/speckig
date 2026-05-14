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

(append after work)
