# 0003 — Markdown-Render im rechten Panel + AJAX-Loader

Blocked by: 0002

## Done when

- Neuer JSON-Endpoint `app/pm.php` (oder Wiederverwendung von `app/file.php` mit `pm/`-erlaubtem Pfad — entscheide selbst, dokumentier im Done):
  - Eingabe: `?path=pm/milestones/.../*.md` oder `?path=pm/bugs/.../*.md`.
  - Output: `{ok, path, html}` mit `html` = Parsedown-Render des Markdown-Inhalts.
  - Pfad-Traversal-Schutz wie in `file.php`. Pfad MUSS mit `pm/` beginnen (kein `app/`, kein Repo-Root).
- Neuer JS-Loader `app/_share/js/plan_loader.js` (oder Erweiterung von `content_loader.js` — pragmatisch entscheiden, aber **keine** Vermischung von Tree-Klick-Handlern und Plan-Klick-Handlern, die sich gegenseitig stoeren):
  - Klick auf einen Sidebar-Link in `plan.php` -> AJAX an den Endpoint -> rendert Markdown im `<article id="content">`.
  - URL-Sync via `history.pushState` (`/plan.php?path=...`).
  - Bookmark/Reload mit `?path=...` rendert die richtige Seite (initial-Load wie in `content_loader.js`).
  - Sidebar-Link wird visuell als aktiv markiert (CSS-Klasse `active`), wenn sein `?path` der aktuelle Pfad ist.
- Im rechten Panel oberhalb des Markdown wird ein kleiner Header gezeigt: bei Tickets `Status: open|done`, bei Milestones `Status: <status>`. Status wird aus dem Markdown extrahiert (Tickets haben kein eigenes `Status:`-Feld — leite aus dem Pfad ab: `open/` -> `open`, `archive/` -> `done`. Milestones haben `Status:`-Zeile.).
- Markdown-Render via vorhandenem `Parsedown` (`app/_share/vendor/Parsedown.php`).
- Smoketest gegen `php -S 127.0.0.1:8099 -t app`:
  - `curl -s "http://127.0.0.1:8099/pm.php?path=pm/milestones/005-spec-parser/milestone.md"` liefert JSON mit `html` enthaelt `<h1>` und Ticket-Liste.
  - `curl -s "http://127.0.0.1:8099/pm.php?path=app/index.php"` -> 400 (nicht unter `pm/`).
  - `curl -s "http://127.0.0.1:8099/pm.php?path=pm/../etc/passwd"` -> 400.
- `@spec`-Bloecke an neuen Funktionen / Endpoint.
- Box im milestone.md fuer 0003 abhaken. Wenn alle drei Tickets durch sind: `Status: done`, Milestone-Folder via `git mv pm/milestones/006-... pm/milestones/archive/006-...` im selben Commit.

## Verifikation

1. `php -l app/pm.php` (oder `file.php`, falls erweitert) sauber.
2. JS-Konsole im Browser zeigt keine Errors beim Klick auf einen Sidebar-Link.
3. Klick auf `005-spec-parser/milestone.md` -> rechts Markdown sichtbar, Ueberschrift, Ticket-Liste.
4. Klick auf ein archiviertes Ticket -> rechts Inhalt + Status-Header `done`.
5. Klick auf ein offenes Ticket -> Status-Header `open`.
6. Bookmark: `/plan.php?path=pm/milestones/005-spec-parser/milestone.md` direkt aufrufen -> Sidebar + rechts schon gerendert.
7. Server stoppen.

## Out of scope

- Editieren / Bearbeiten der Markdown-Files (read-only).
- Spezialisierter Ticket-Renderer (Done-when als interaktive Liste etc.).
- Commits-View — eigener spaeterer Milestone.
