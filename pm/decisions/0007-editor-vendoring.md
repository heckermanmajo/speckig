# 0007 — Editor-Vendoring

- Editor-Library ist CodeMirror 5.65.21, gewaehlt nach `pm/reports/0002-editor-library.md`.
- Vendor-Pfade sind `app/_share/vendor/js/codemirror.min.js`, `app/_share/vendor/js/codemirror-modes/<lang>.js` und `app/_share/vendor/css/codemirror.css`.
- In M012 wird nur der `markdown`-Mode vendored; weitere Modes (`php`, `javascript`, `ts`, …) folgen ab M013.
- Zusaetzliche Modes werden lazy nachgezogen: erst vendoren, wenn ein File-Typ sie braucht.
- Pre-minifizierte Upstream-Files werden 1:1 uebernommen, der Original-Header bleibt unveraendert.
- Mit M013 zusaetzlich vendored: php, javascript, clike, shell, css, xml, yaml, htmlmixed (Dependency von php).
