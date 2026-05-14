# 0001 — CodeMirror 5 vendoren + Decision

CodeMirror 5 als Vendor-Drop-in wie in `pm/reports/0002-editor-library.md`
empfohlen. Nur Core + Markdown-Mode + zugehoeriges CSS — alles weitere
folgt in spaeteren Milestones (M013/M014).

## Done when
- Drei Vendor-Files:
  - `app/_share/vendor/js/codemirror.min.js` (von
    `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.min.js`).
  - `app/_share/vendor/js/codemirror-modes/markdown.js` (von
    `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/markdown/markdown.js`).
  - `app/_share/vendor/css/codemirror.css` (von
    `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.css`).
- MIT-Originalheader bleibt unveraendert; keine Minifizierung selbst,
  keine Modifikation.
- Neue Decision-Datei `pm/decisions/0007-editor-vendoring.md` mit
  einem Satz pro Beschluss: Library, Version, Vendor-Pfad,
  Sprach-Set bei M012 (nur markdown), Mode-Lazy-Load-Strategie.
- `pm/decisions/0002-editor-library.md` bleibt unangetastet
  (append-only — die neue Decision ergaenzt, ueberschreibt nicht).
- Smoke-Page: kein UI-Hook noch — aber `curl -I` auf die drei
  Vendor-URLs unter `127.0.0.1:8086` liefert 200 + passenden
  Content-Type.

## Verifikation
- `php -S 127.0.0.1:8086 -t app` starten.
- `curl -sI http://127.0.0.1:8086/_share/vendor/js/codemirror.min.js | head -1` → `HTTP/1.1 200 OK`.
- `curl -sI http://127.0.0.1:8086/_share/vendor/js/codemirror-modes/markdown.js | head -1` → `HTTP/1.1 200 OK`.
- `curl -sI http://127.0.0.1:8086/_share/vendor/css/codemirror.css | head -1` → `HTTP/1.1 200 OK`.
- `head -5 app/_share/vendor/js/codemirror.min.js` enthaelt MIT-Header.
- `cat pm/decisions/0007-editor-vendoring.md` zeigt 1-Satz-Decisions, kein Romantext.
- Streu-File-Check und Server-Cleanup wie immer.

## Out of scope
- Weitere Modes (PHP, JS, …) — kommen in M013 dazu.
- Theme-Files — Default-Theme reicht.
- IIFE-Wrapper (kommt in 0003).
