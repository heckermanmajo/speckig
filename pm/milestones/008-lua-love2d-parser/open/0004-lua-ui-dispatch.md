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

(append after work)
