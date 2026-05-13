# 0004 — JSON-Endpoint /file.php

Server-Backend für den AJAX-Content-Load. Macht das Markdown-Rendering als reines Datenendpoint verfügbar.

See: pm/decisions/0004-ux-policy.md

## Done when
- Datei `app/file.php` existiert.
- `GET /file.php?path=<relpfad>` antwortet mit `Content-Type: application/json`.
- Erfolg: `{ "ok": true, "path": "<relpfad>", "html": "<gerendertes html>" }`.
  - `.md` → Parsedown
  - andere → `<pre>` + `app::escape`
- Fehler (ungültiger Pfad, nicht-existent, traversal-versuch): `{ "ok": false, "message": "Ungültiger Pfad." }` + HTTP 400.
- Pfad-Traversal-Check identisch zu `index.php` (Realpath unter `$speckig_root_abs`).
- Smoketest: `curl -s "http://127.0.0.1:8080/file.php?path=pm/decisions/0002-php-infra.md" | head` zeigt JSON mit `html`-Feld; `curl -s "http://127.0.0.1:8080/file.php?path=../README.md"` zeigt 400 + Fehler-JSON.

## Done
- Neu: `app/file.php` (105 Zeilen) — JSON-Endpoint mit `Content-Type: application/json`, drei-Schicht-Pfad-Validation (String-Safety, Realpath-Containment, `is_file`), `.md` via Parsedown, sonst `<pre>` + `app::escape`.
- Test A (gueltige md): HTTP 200, `ok:True path:pm/decisions/0002-php-infra.md html-starts:<h1>0002 — PHP infra</h1>...`.
- Test B (PHP-Source als non-md): HTTP 200, `ok:True has-pre:True escaped-php:True` (`<pre>&lt;?php...`).
- Test C (Traversal `../README.md`): HTTP 400, `{"ok":false,"message":"Ungueltiger Pfad."}` — kein README-Inhalt im Body.
- Test D (nicht-existent `pm/nope.md`): HTTP 400, `{"ok":false,"message":"Ungueltiger Pfad."}`.
- Test E (kein `path`-Param): HTTP 400, `{"ok":false,"message":"Ungueltiger Pfad."}`.
- Test F (Header-Check): `Content-Type: application/json` gesetzt — auch im Fehlerfall.
- Konsolidierungs-Hinweis: Validation-Logik (`$speckig_root_abs`, `$path_string_is_safe`, `$resolved_path_abs`, `$path_is_inside_root`, `$path_points_to_file`, `$path_is_valid`) ist 1:1 zu `app/index.php` dupliziert — Variablen-Namen identisch gehalten, damit Ticket 002/0006 (clean-server-render) das in einen gemeinsamen Helper ziehen kann.
