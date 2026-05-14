# 0003 — Edit/Save/Cancel im Code-Tab der Tree-View

Edit-Button im Code-Tab der Tree-View. Click toggelt das `<pre>` gegen
einen CodeMirror-Editor (Mode aus Extension). Save speichert via
M013/0001-Endpoint. Cancel restauriert das `<pre>`.

## Done when
- `app/file.php` (GET) liefert zusaetzlich `raw` (rohen Datei-Inhalt)
  und `editable` (bool) zurueck.
  - `editable=false` wenn Pfad in der Schwarzliste (vendor / .git /
    spec_parser).
  - Sonst `editable=true`.
- `app/_share/css/app.css` bekommt Tree-View-Toolbar-Regeln (Wieder-
  verwendung der `.content-toolbar`-Klasse aus M012 — keine neuen
  Klassen-Namen noetig). Falls ein dezenter visueller Unterschied
  gewuenscht: `.content-tabs .content-toolbar { ... }` — sparsam.
- `app/_share/js/content_loader.js`:
  - Modul-State: `current_raw`, `current_path`, `current_editable`,
    `edit_is_active`.
  - Nach erfolgreichem fetch werden raw/path/editable in State
    geschrieben, edit_is_active auf false zurueck.
  - Nach `render_tabs_shell(...)` und `attach_tab_handlers(...)`:
    falls `current_editable` true, in den Code-Tab-Panel ein
    `<div class="content-toolbar">` mit Edit-/Save-/Cancel-Buttons
    einfuegen (analog plan_loader.js, Klassen `.btn-edit`,
    `.btn-save`, `.btn-cancel`).
  - Edit-Click:
    1. Mode via `speckig_editor.extension_to_mode(extension_of(current_path))`.
    2. Code-Panel-Inhalt (`<pre>`) wegnehmen, aber merken
       (`const previous_pre_html = panel.innerHTML;` — sodass Cancel
       sauber restauriert).
    3. Mount-Div einsetzen, `speckig_editor.mount(mount_div, current_raw,
       current_path, mode_name)`.
    4. Toolbar-Buttons toggeln (Edit hidden, Save+Cancel sichtbar).
    5. Tab-Switch auf Code (falls noch Spec aktiv).
  - Save-Click:
    - `await window.speckig_editor.save(current_path)`. Da der
      Save-Endpoint jetzt M013/0001 ist (Pfad-Heuristik: alles ausserhalb
      `pm/` geht an `file.php?action=save`), passt **die bestehende
      `speckig_editor.save()` aus M012 NICHT** — sie POSTet hart an
      `/pm.php?action=save`.
      **Loesung**: editor.js bekommt eine zweite save-Variante.
      Konkret: `speckig_editor.save(path)` route nach Pfad —
      `path.startsWith("pm/")` → `/pm.php?action=save`, sonst
      `/file.php?action=save`. Spec-Block in editor.js entsprechend
      erweitern.
    - Bei `ok:true`: Editor zerstoeren, Re-Load via `load_path(...)`.
    - Bei Fehler: `.toolbar-error`-Span im Code-Tab zeigen.
  - Cancel-Click: Editor zerstoeren, Re-Load.
- Spec-Tab bleibt vollkommen unberuehrt (Edit findet nur im Code-Tab
  statt).

## Verifikation
- `php -l app/file.php` clean.
- `node --check app/_share/js/content_loader.js` und
  `node --check app/_share/js/editor.js` clean.
- Server 8086.
- `curl -s 'http://127.0.0.1:8086/file.php?path=app/_share/css/app.css' | grep -c '"raw":'` → 1.
- `curl -s 'http://127.0.0.1:8086/file.php?path=app/_share/css/app.css' | grep -c '"editable":true'` → 1.
- `curl -s 'http://127.0.0.1:8086/file.php?path=app/_share/vendor/Parsedown.php' | grep -c '"editable":false'` → 1.
- `grep -c 'content-toolbar' app/_share/js/content_loader.js` ≥ 1.
- `grep -c 'extension_to_mode' app/_share/js/content_loader.js` ≥ 1.
- `grep -c 'startsWith(\"pm/\")' app/_share/js/editor.js` ≥ 1 (Pfad-Routing).
- **Roundtrip end-to-end** (ohne Browser):
  - `cp app/_share/css/app.css /tmp/app.css.bak`.
  - `curl -s -X POST --data '/* claude */' 'http://127.0.0.1:8086/file.php?action=save&path=app/_share/css/app.css'` → `"ok":true`.
  - GET liefert `raw` enthaelt `/* claude */`.
  - Restore. `git diff app/_share/css/app.css` leer.
- M012-Regression: `/plan.php` Edit-Flow (zumindest GET) bleibt grün —
  `curl -s 'http://127.0.0.1:8086/pm.php?path=pm/ideas/talk_about_code_base.md' | grep -c '"raw":'` → 1.

## Out of scope
- Tab-Persistenz waehrend Edit (Editor lebt nur im Code-Tab; Wechsel
  in Spec-Tab waehrend Edit ist undefined — Toolbar-Buttons bleiben
  sichtbar).
- Mehrere offene Buffer.
- Confirm bei Cancel mit ungespeicherten Aenderungen.
- Keyboard-Shortcut.
