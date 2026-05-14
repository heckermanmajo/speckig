# 0005 — Aktion "Datei loeschen"

"Loeschen"-Button neben dem Edit-Button im Code-Tab-Toolbar. JS-
`confirm()`-Prompt. POST `?action=delete_file&path=...`. Nur Dateien,
keine Verzeichnisse, Schwarzliste greift.

## Done when
- `app/file.php` neuer POST-Pfad `?action=delete_file`:
  - `?path=...`-Validierung wie Save (SPECKIG_ROOT, Schwarzliste).
  - `is_file($abs)` true — sonst 400 `Pfad ist keine Datei.`.
  - `unlink($abs)` — bei Fehler 500.
  - Antwort `{"ok":true,"path":...}`.
- `app/_share/js/content_loader.js`: zusaetzlicher Button
  `<button class="btn-delete">Delete</button>` in der Toolbar des
  Code-Tabs, neben Edit. Sichtbar nur wenn `current_editable` true.
- Click-Handler `on_delete_click`:
  - `confirm("Diese Datei wirklich loeschen?")` — bei Abbruch return.
  - Fetch POST.
  - Bei `ok:true`: `show_initial_placeholder()` aufrufen (Content-
    Panel zurueck auf Platzhalter) und `window.location.reload()`
    fuer Tree-Refresh.
  - Bei Fehler: `.toolbar-error` mit Server-Message.
- Editor-Modus ueberschreibt `btn-delete` nicht — d.h. wenn `edit_is_active`,
  ist der Delete-Button hidden (Save/Cancel sind dann sichtbar).

## Verifikation
- `php -l app/file.php` clean.
- Server 8086.
- **Setup**: `curl -s -X POST -H 'Content-Type: application/json' -d '{"dir":"app/_share/css","name":"delete-smoke.css"}' 'http://127.0.0.1:8086/file.php?action=new_file'` → ok:true.
- **Happy path**:
  - `curl -s -X POST 'http://127.0.0.1:8086/file.php?action=delete_file&path=app/_share/css/delete-smoke.css'` → `"ok":true`.
  - `ls app/_share/css/delete-smoke.css` schlaegt fehl.
- **Guards (400)**:
  - Verzeichnis: `path=app/_share/css` → 400 `Pfad ist keine Datei.`.
  - Vendor-Datei: `path=app/_share/vendor/Parsedown.php` → 400.
  - .git: `path=.git/HEAD` → 400.
  - Existiert nicht: `path=app/_share/css/no-such.css` → 400.
  - Traversal: `path=../../etc/passwd` → 400.
- **Markup**: `grep -c 'btn-delete' app/_share/js/content_loader.js` ≥ 1.
- **Regression**: Save-Endpoint und new_file-Endpoint bleiben funktional.
- `git status` clean nach allen Tests.

## Out of scope
- Bulk-Delete.
- Trash / Undo.
- Verzeichnisse loeschen.
- Undo-Buffer im Editor leeren bei Delete.
