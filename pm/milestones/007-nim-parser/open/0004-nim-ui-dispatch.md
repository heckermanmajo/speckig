# 0004 — Nim im file.php-Dispatch + UI-Smoketest

Blocked by: 0002

## Done when

- `app/file.php` ruft `spec_parser::parse()` auch fuer `.nim`-Dateien — heutiger Dispatch ist sprach-agnostisch (laeuft ueber den zentralen `spec_parser`), aber pruefen dass der Pfad funktioniert. Falls `file.php` heute Endungen vorfiltert: `nim` ergaenzen.
- Spec-View im Frontend (`content_loader.js`-Render) bleibt unveraendert — das Schema ist gleich, der Renderer rendert `kind`-Werte generisch. Ggf. CSS-Klasse `spec-view-symbol-proc` / `spec-view-symbol-object` ergaenzen falls visuelle Unterscheidung gewollt (V1: nicht noetig).
- Smoketest: `php -S 127.0.0.1:8099 -t app`, eine Mini-`.nim`-Demo-Datei ins Repo legen unter `app/_share/spec_parser/tests/fixtures/nim/demo.nim` (oder eine der bestehenden Fixtures), `curl ?path=...nim` liefert JSON mit `spec.language=nim` und Symbolen.
- Browser-Sicht (manuell): Spec-Tab zeigt Datei-Spec + Symbole.
- Box im milestone.md fuer 0004 abhaken; wenn das das letzte Ticket ist, M007 archivieren via `git mv pm/milestones/007-... pm/milestones/archive/007-...` im selben Commit.

## Out of scope

- Migration echten Nim-Codes — kein Nim-Code im Repo, nur Test-Fixtures.
- Nim-spezifisches UI-Styling (eigene Farben fuer proc vs. func etc.).

## Done

(append after work)
