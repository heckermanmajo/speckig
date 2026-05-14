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

(append after work)
