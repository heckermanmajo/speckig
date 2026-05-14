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

## Done
- `app/file.php` (GET):
  - Spec-Block oben um `raw` und `editable` in der Response-Form erweitert
    und einen Hinweis darauf gepflanzt, dass die Editier-Schwarzliste
    jetzt doppelt lebt (POST-Save + GET) — Konsolidierung ist Folge-Ticket,
    bewusst inline, um Premature-Abstraktion zu vermeiden (siehe
    code_style.md).
  - Direkt vor der success-Response berechnen wir das `editable_flag` aus
    drei Schwarzliste-Checks (vendor, .git, spec_parser), je als Prefix
    UND als Substring (defensive Schicht — analog zum POST-Save-Block).
  - JSON-Response um `"raw" => $raw_file_contents` (roher Datei-Inhalt)
    und `"editable" => $editable_flag` erweitert. `path`, `html` und
    `spec` bleiben unveraendert.
- `app/_share/js/editor.js`:
  - Spec-Block oben um die neue Routing-Heuristik in `save()` erweitert:
    `pm/`-Pfade gehen weiter an pm.php (M012/0002), alles andere an
    file.php (M013/0001). Das macht den Tree-View-Save moeglich, ohne
    die Plan-/Info-View-Saves zu brechen.
  - `save(path)` baut die `fetch_url` jetzt aus einer ternaeren
    Routing-Entscheidung — `path.indexOf("pm/") === 0` triggert pm.php,
    sonst file.php. Rest der Funktion (POST, JSON-Parsing, Fehlerschema)
    unveraendert.
- `app/_share/js/content_loader.js` (massiv erweitert um den Edit-Flow):
  - Spec-Block oben dokumentiert den neuen Tree-View-Edit-Flow:
    Toolbar im Code-Tab-Panel, Mode-Auswahl aus Extension via
    `speckig_editor.extension_to_mode()`, Save-Routing in editor.js,
    Spec-Tab bleibt unangetastet.
  - Modul-State: `current_raw`, `current_path`, `current_editable`,
    `edit_is_active`, `previous_code_pre_html`.
  - `load_path()` schreibt nach erfolgreichem Fetch raw/path/editable
    in den State (BEVOR DOM-Update), setzt `edit_is_active = false`
    und ruft `render_code_toolbar(article_element)` nur wenn
    `current_editable === true` — Schwarzliste-Pfade bekommen keine
    Toolbar.
  - `render_code_toolbar()` findet das Code-Panel
    (`.content-tab-panel[data-panel="code"]`), baut eine
    `.content-toolbar` mit drei Buttons (`.btn-edit`, `.btn-save`,
    `.btn-cancel`, Save+Cancel `hidden` initial) und haengt sie ans
    Ende des Code-Panels.
  - `on_edit_click()`: editor-Verfuegbarkeit (mount + extension_to_mode)
    pruefen, `<pre>` aus dem Code-Panel rausnehmen (HTML in
    `previous_code_pre_html` merken), eventuelle alte
    `.toolbar-error` raeumen, Code-Tab aktivieren (falls Spec offen
    war), Mount-Container VOR die Toolbar einsetzen, Mode aus
    `current_path`-Extension ableiten und
    `speckig_editor.mount(mount_div, current_raw, current_path,
    mode_name)` aufrufen. `edit_is_active = true`,
    Buttons toggle.
  - `on_save_click()`: `await speckig_editor.save(current_path)` —
    Routing macht editor.js (pm.php vs file.php). Bei `ok:true`
    `destroy()` + `load_path(current_path, false)`. Bei Fehler
    `.toolbar-error` direkt ueber der Toolbar einhaengen; Editor
    bleibt offen, Buffer erhalten. Server-`message` wird angezeigt,
    sonst "Speichern fehlgeschlagen.".
  - `on_cancel_click()`: `speckig_editor.destroy()` + Re-Load —
    Re-Load stellt das `<pre>` aus der frischen GET-Response
    wiederher; `previous_code_pre_html` ist defensiver Fallback.
  - `toggle_toolbar_buttons(toolbar_node)` befragt das DOM nicht
    nach Sichtbarkeit, sondern liest `edit_is_active` und setzt
    `hidden`-Flags deterministisch.
  - `activate_code_tab(article_element)`: setzt die `active`-Klasse
    auf den Code-Tab-Button und das Code-Panel und entfernt sie
    von allen anderen — damit der Edit-Modus visuell immer im
    Code-Tab landet.
  - `extract_extension(path)`: einfache lastIndexOf(".")-Extraktion,
    Fallback "" bei nicht-string oder kein Punkt im Pfad.
  - `show_initial_placeholder()` und `show_invalid_path_message()`
    setzen `current_raw`/`current_path`/`current_editable`/
    `edit_is_active`/`previous_code_pre_html` zurueck (analog
    plan_loader.js).
  - Defensive Guards: `speckig_editor` wird vor jedem Aufruf auf
    Verfuegbarkeit + Funktions-Typ geprueft (warnt via console.warn).
- `app/index.php`:
  - `codemirror.css` plus alle vendored Modes
    (markdown/xml/javascript/css/clike/htmlmixed/php/shell/yaml) und
    `editor.js` werden jetzt VOR `content_loader.js` geladen — analog
    plan.php/info.php. Reihenfolge: codemirror.min.js zuerst, dann
    Modes (xml VOR htmlmixed VOR php wegen Mode-Dependencies), dann
    editor.js, dann content_loader.js.
- `app/_share/css/app.css`: nicht angefasst — die Toolbar-Klassen
  (`.content-toolbar`, `.toolbar-error`, `.editor-mount`) leben seit
  M012/0004 dort und werden 1:1 wiederverwendet.
- Style: BSD-Klammern auf eigener Zeile, snake_case, eine Bedingung
  pro Zeile mit sprechenden Bool-Namen (`path_is_vendor`,
  `path_is_git`, `path_is_spec_parser`, `current_editable`,
  `editor_is_available`, `save_ok`, `toolbar_is_complete`,
  `toolbar_is_present`, `panel_exists`, `editor_can_be_destroyed`,
  `path_is_pm`, ...).

### Verifikation
- `php -l app/file.php` → clean.
- `node --check app/_share/js/content_loader.js` → clean.
- `node --check app/_share/js/editor.js` → clean.
- Server `php -S 127.0.0.1:8086 -t app` als Smoke-Server, 8083 nicht
  angefasst, sauber via `TaskStop` beendet.
- `raw`+`editable`-Smoketest:
  - `app/_share/css/app.css` → `"raw":` x1, `"editable":true` x1.
  - `app/_share/vendor/Parsedown.php` → `"editable":false` x1.
  - `app/_share/vendor/js/codemirror.min.js` → `"editable":false` x1.
  - `app/_share/spec_parser/spec_parser.php` → `"editable":false` x1.
- JS-Hooks im content_loader.js:
  - `content-toolbar` = 4.
  - `extension_to_mode` = 3.
  - `btn-edit/btn-save/btn-cancel` = 6.
  - `current_raw/current_editable` = 10.
- Endpoint-Routing in editor.js: 5 Vorkommen von
  `pm.php?action=save` bzw. `file.php?action=save` (Spec-Block-
  Kommentar, beide save()-Branches, plus Doku).
- Roundtrip via file.php auf `app/_share/css/app.css`:
  POST `/* claude tree */` → `{"ok":true,...,"bytes":17}`; GET-raw
  enthaelt `claude tree`; Restore aus `/tmp/app.css.bak`;
  `git diff app/_share/css/app.css` clean.
- M012-Regression — Roundtrip via pm.php auf
  `pm/ideas/talk_about_code_base.md`: POST `via-pm` →
  `{"ok":true,...,"bytes":6}`; GET-raw enthaelt `via-pm`; Restore;
  `git diff` clean. Plan-View-Save bleibt also gruen.
- Spec-Payload bleibt: `app/pm.php` → `"spec":` x1.
- HTTP 200 fuer `/index.php`, `/plan.php`, `/info.php`.
- Streu-File-Check: nur kanonisches `./app.sqlite`, keine
  `*.tmp.*`-Files im Repo.

Files touched:
- `app/file.php` (Spec-Block + editable-Block + Response-Felder).
- `app/index.php` (codemirror.css + Modes + editor.js eingebunden).
- `app/_share/js/editor.js` (save-Routing + Spec-Block).
- `app/_share/js/content_loader.js` (Edit-Flow komplett).
- `pm/milestones/013-edit-files-cm/milestone.md` (Haekchen + Pfad).
- Ticket selbst nach `archive/`.

Browser-Smoketest ist Sache des Users.
