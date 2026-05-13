# 0003 — Naming (supersedes 0002-Naming)

- Supersedes die Naming-Zeile in `pm/decisions/0002-php-infra.md`.
- Naming Variablen: `snake_case`.
- Naming Funktionen/Methoden: immer `snake_case`, auch statische — kein `camelCase` mehr.
- Naming Klassen, instanziierbar (Datenklassen, Actions, Exceptions): `PascalCase`.
- Naming Klassen, nur statische Methoden (`app`, `db`, `document`, `cards`): `lowercase` / `snake_case`.
- Heuristik: `new Foo()` → `PascalCase`; nur `foo::bar()` → lowercase.
