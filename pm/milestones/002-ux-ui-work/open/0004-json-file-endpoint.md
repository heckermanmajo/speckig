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
