# 0004 — Lua im file.php-Dispatch + UI-Smoketest

Blocked by: 0002

## Done when

- `app/file.php` ruft Parser auch fuer `.lua`. Sprach-Dispatch laeuft zentral ueber `spec_parser`, Pfad pruefen.
- Spec-View rendert generisch ueber `kind`-Werte. Falls visuelle Unterscheidung fuer Lua gewuenscht: CSS-Klasse `spec-view-symbol-function` / `spec-view-symbol-table` ergaenzen — V1 nicht noetig.
- Smoketest: `php -S 127.0.0.1:8099 -t app`, eine Demo-`.lua`-Fixture per `?path=...` aufrufen, Spec-View zeigt Symbole.
- Box abhaken; falls letztes Ticket: Milestone-Folder via `git mv` nach `archive/`.

## Out of scope

- Migration echten Lua-Codes.
- Love2D-spezifisches UI (Asset-Vorschau, Live-Render).

## Done

- `app/file.php`: Endung `lua` zur Spec-Whitelist (`$language_is_supported_for_spec`)
  ergaenzt, analog zu `nim` aus M007/0004. Genau eine Zeile zusaetzlich, kein
  weiterer Code-Pfad noetig — der Dispatcher in `spec_parser::parse` kennt
  `.lua` bereits aus 0001/0002.
- Generischer Renderer im Frontend reicht: alle erzeugten `kind`-Werte
  (`method`, `function`, `local-function`, `table`, `field`, `local-var`,
  `local`) werden ueber den vorhandenen Spec-View gerendert. Kein
  zusaetzliches CSS angefasst (Out of scope laut Ticket).
- Demo: vorhandene Fixture
  `app/_share/spec_parser/tests/fixtures/lua/love2d_callbacks.lua` (Option a,
  wie bei Nim).

### Smoketest gegen `php -S 127.0.0.1:8099 -t app`

`?path=app/_share/spec_parser/tests/fixtures/lua/love2d_callbacks.lua`:

```
ok: True
spec.language: lua
file_spec: ['Love2D demo entry: load assets, advance position, draw the world, react to input.']
symbols: [('method', 'love.load'), ('method', 'love.update'), ('method', 'love.draw'), ('method', 'love.keypressed')]
```

`?path=app/_share/spec_parser/tests/fixtures/lua/no_spec.lua` (Backend-Filter
"kein Mehrwert" greift, weil weder `file_spec` noch ein Symbol eine
`@spec`-Zeile traegt):

```
spec is None? True
```

### Verifikation

- `php -l app/file.php`: No syntax errors detected.
- `php app/_share/spec_parser/tests/run.php`: 27/27 passed
  (php 6/6, js 4/4, nim 7/7, lua 8/8) — keine Regression.
- Streu-File-Check: nur kanonisches `./app.sqlite` (gitignored) vorhanden.
- Server `php -S 127.0.0.1:8099 -t app` per `TaskStop` beendet (Port 8083
  nicht beruehrt).
