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

### file.php-Dispatch

- **Beobachtung**: `app/file.php` hatte eine Endungs-Whitelist (`$language_is_supported_for_spec = $file_extension === "php" || $file_extension === "js";`, Zeilen 145-147). Der Dispatcher in `spec_parser.php` selbst kennt `.nim` seit M007/0001, aber `file.php` gab den Pfad fuer `.nim`-Dateien gar nicht erst an `spec_parser::parse()` weiter -> `spec_payload` blieb `null`.
- **Aenderung**: `|| $file_extension === "nim"` ergaenzt. Minimaler Diff, gleiche Form wie die bestehenden Aestchen.
- Andere Felder (`spec_walker`-Filter, Error-Handling) bleiben unveraendert; sie sind sprach-agnostisch und greifen jetzt auch fuer Nim.

### Demo-Datei

- **Wahl: Option a** — die bestehende Fixture `app/_share/spec_parser/tests/fixtures/nim/proc_with_conditions.nim` dient als Demo. Im Browser-Tree erreichbar via `?path=app/_share/spec_parser/tests/fixtures/nim/proc_with_conditions.nim`. Keine neue Datei angelegt — `tests/fixtures/nim/` ist bereits Teil des Repos und der Tree zeigt sie an.

### CSS

- Nichts geaendert. Spec-View rendert generisch ueber `kind`, V1-Anforderung erfuellt. Optionale `spec-view-symbol-{proc,object,...}`-Klassen wurden bewusst NICHT ergaenzt (out of scope laut Ticket: "V1: nicht noetig").

### content_loader.js

- Nichts geaendert (per Ticket-Vorgabe explizit out of scope — Renderer ist generisch).

### Verifikations-Belege

- `php -l app/file.php` -> `No syntax errors detected in app/file.php`.
- `php app/_share/spec_parser/tests/run.php` -> `19/19 passed` (php: 6/6, js: 4/4, nim: 7/7) — keine Regression.
- Server: `php -S 127.0.0.1:8099 -t app` (Bash background, NICHT 8083). Nach den Curls sauber via TaskStop gestoppt.
- `curl -s "http://127.0.0.1:8099/file.php?path=app/_share/spec_parser/tests/fixtures/nim/proc_with_conditions.nim"`:
  - `ok: True`
  - `spec.language: nim`
  - `file_spec: ['Top-level helper used to validate that proc-level specs with multiple condition lines parse correctly.']`
  - `first symbol: proc validate_user_input`
- `curl -s "http://127.0.0.1:8099/file.php?path=app/_share/spec_parser/tests/fixtures/nim/no_spec.nim"`:
  - `spec is None? True` (raw spec: `None`)
  - **Beobachtung zum Backend-Filter**: `no_spec.nim` enthaelt zwar Symbole (`type Bare = object`, `proc bare_helper`), aber weder `file_spec` noch irgendein Symbol traegt eine `@spec`-Zeile. Der `spec_walker`-Filter in `file.php` (M005/0005) kondensiert das korrekt auf `null` — kein Mehrwert, keine ueberfluessige Spec-View. Verhalten ist konsistent mit dem PHP/JS-Pendant `no_spec.php`/`no_spec.js`. Sinnvoll, nichts zu fixen.
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).
- Server gestoppt (TaskStop, Port 8099). Port 8083 (User-Session) NICHT angefasst.
- Keine neuen Dateien (Demo nutzt bestehende Fixture).
