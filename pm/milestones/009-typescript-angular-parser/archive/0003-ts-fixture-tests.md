# 0003 — TS-Fixture-Tests

Blocked by: 0002

## Done when

- Verzeichnis `app/_share/spec_parser/tests/fixtures/ts/` mit Fixture-Paaren `<name>.ts` + `<name>.expected.json`.
- Mindestens diese Fixtures:
  - `angular_component.ts` — `@Component({...}) export class AppComponent` mit Datei-Spec, Klassen-Spec, Properties + Methoden mit Spec.
  - `angular_service.ts` — `@Injectable() export class FooService` mit ctor und Methoden.
  - `interface_with_spec.ts` — `interface User { id: number; name: string }` mit Spec.
  - `enum_with_spec.ts`.
  - `function_with_generics.ts` — `function map<T, U>(xs: T[], fn: (t:T)=>U): U[]` mit Spec; Generics werden als Source-String mitgefuehrt, nicht analysiert.
  - `template_literal.ts` — Backtick-String mit `// @spec`-Text drin -> NICHT als Spec geparst.
  - `regex_literal.ts` — Regex-Literal mit `//` darin -> NICHT als Spec geparst (Tokenizer-Test).
  - `decorator_with_args.ts` — Decorator-Args als Source-String erhalten.
  - `dangling_spec.ts`.
  - `local_spec.ts`.
  - `no_spec.ts`.
- Test-Runner integriert TS-Fixtures, Aufruf zeigt `ts: X/Y passed`.
- `php -l` sauber.

## Out of scope

- `.tsx` Fixtures.
- Andere Sprach-Fixtures.

## Done

- **Fixture-Verzeichnis** `app/_share/spec_parser/tests/fixtures/ts/`
  angelegt, 11 Fixture-Paare (jeweils `<name>.ts` + `<name>.expected.json`)
  manuell verfasst und gegen den realen `ts_parser` verifiziert. Stilform
  uebernommen aus `tests/fixtures/lua/` (Datei-Header-Spec, Pretty-printed
  expected.json, einheitliche Section-Ueberschriften).

- **Fixture-Liste** (alle gegen `php app/_share/spec_parser/index.php
  <fixture>` gegengeprueft, expected.json reflektiert exakt den realen
  Parser-Output):

  - `angular_component.ts` — Datei-Header-Spec, Klassen-Spec mit
    `@Component({...})`-Decorator (`args_source` mit Inhalt), zwei
    Properties mit Spec (`user: User | null = null` und
    `next_url: string = ""`), `@LogCall()`-decorierte
    `async login(...): Promise<void>`-Methode, plus eine
    `logout(): void`-Methode ohne Decorator. Exercises: Datei-Spec,
    Klassen-Spec mit Decorator, Properties (mit Type/Default), Methoden
    (mit/ohne Decorator).
  - `angular_service.ts` — `@Injectable() export class FooService` mit
    `constructor(private http: HttpClient)`, `sign_in(...)` und
    `sign_out(...)`. Exercises: Klassen-Decorator mit `args_source: ""`,
    Konstruktor (`kind: "constructor"`), zwei Methoden mit Spec.
  - `interface_with_spec.ts` — `interface User { id: number; name: string }`
    mit Spec; `expected.json` zeigt `"members": []` weil der TS-Parser
    Interface-Bodies in V1 nicht zerlegt (siehe M009/0002 "Offene Fragen").
  - `enum_with_spec.ts` — `enum Color { Red, Green, Blue }` mit Spec;
    Body wird wie beim Interface in V1 nicht zerlegt.
  - `function_with_generics.ts` — `function map<T, U>(xs: T[],
    fn: (t: T) => U): U[]` mit Spec; Generics als Source-String in
    `signature`. Realer Output: `"function map<T, U>(xs: T[], fn:(t: T) => U): U[]"`
    (Parser komprimiert Whitespace und entfernt das Space vor `(`,
    siehe `normalise_signature`).
  - `template_literal.ts` — Top-Level `const fake_template = \`...\`` mit
    `// @spec`-Text im Backtick-String + danach echte Funktion mit Spec.
    Belegt: Tokenizer erkennt `template_literal` als eigene Klasse,
    Marker-Trigger nur auf `comment_line`.
  - `regex_literal.ts` — Top-Level `let pattern = /\/\/ @spec[\s\S]*?\/\/
    @end-spec/;` + danach echte Funktion mit Spec. Belegt: Tokenizer
    klassifiziert Regex-Literal kontextabhaengig (nach `=` ist `/`
    Regex-Start).
  - `decorator_with_args.ts` — drei Klassen `A`, `B`, `C` mit jeweils
    `@Sealed`, `@LogCall()` und `@Component({selector: "app-c", standalone:
    true})`. Belegt alle drei `args_source`-Faelle: `null`, `""`,
    `"<inhalt>"`.
  - `dangling_spec.ts` — Datei-Header-Spec, eine `function ok(): number`
    mit Spec, danach Spec-Block am Datei-Ende ohne Symbol. Belegt:
    `warnings: ["dangling spec at line 13"]`.
  - `local_spec.ts` — Datei-Spec, `class Compute` mit Method `add(a, b)`,
    deren Body einen lokalen `// @spec`-Block enthaelt. Belegt:
    `members[]` an der Methode mit `kind: "local"` und einem
    `name`-Hint (max. 30 Zeichen vom ersten Spec-Text, wie im Parser
    in `read_function_body` festgelegt).
  - `no_spec.ts` — Klasse `Bare` mit Property + Methode plus Funktion
    `plain` — alles ohne `@spec`-Marker. Symbole erscheinen trotzdem
    in `symbols[]` (Top-Level-Decls werden gesammelt), `spec`-Arrays
    sind leer.

- **Test-Runner** `app/_share/spec_parser/tests/run.php` um TS-Fixtures
  erweitert: neuer `ts_glob`/sort/`groups`-Eintrag, `preferred_order`
  ergaenzt um `"ts"`, alle drei Header-Spec-Bloecke (Top-Level + zwei
  Hilfsfunktionen) auf `php/js/nim/lua/ts` aktualisiert.

### Verifikations-Belege

- `php -l app/_share/spec_parser/tests/run.php` -> "No syntax errors detected".

- `php app/_share/spec_parser/tests/run.php` -> exit 0,
  `38/38 passed`. Pro-Sprache:

  ```
  php: 6/6
  js: 4/4
  nim: 7/7
  lua: 8/8
  ts: 11/11
  ```

  Keine Regression — die alten 27/27 (php 6, js 4, nim 7, lua 8) plus
  zwei synthetische Tests bleiben gruen, neu sind die 11 TS-Fixtures.

- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).

- Out-of-scope eingehalten: kein Touch an `app/file.php` (das ist
  M009/0004), kein Touch an `ts_parser.php` (M009/0002 ist durch und
  hatte keine Bugs in den 11 Fixtures), kein Touch an README, keine
  anderen Sprach-Fixtures angefasst, keine `.tsx`-Fixtures.

### Parser-Bugs

Keine. Der Parser produziert fuer alle 11 Fixtures exakt das, was die
Doku in `## TypeScript` (README) versprochen hat. Eine Beobachtung
ohne Bug-Charakter: `normalise_signature` entfernt das Space zwischen
einem Param-Namen und seinem Typ-Klammer-Tupel (`fn: (t: T)` wird zu
`fn:(t: T)` in der Signatur). Das ist konsistent — das Parser-Verhalten
schluckt Whitespace vor `(` per Decision in `normalise_signature` (analog
zu `js_parser`). Die `expected.json` reflektiert das, kein Fix noetig.
