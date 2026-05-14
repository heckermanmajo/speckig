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

(append after work)
