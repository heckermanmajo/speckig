# 0004 — Edit/Save/Cancel-Buttons im Plan- und Info-Content

Edit-/Save-/Cancel-Buttons im rechten Content-Panel von `plan.php` und
`info.php`. Klick auf `Edit` mountet den Editor (siehe 0003) mit dem
aktuell geladenen Markdown-Rohinhalt; `Save` schickt an `pm.php`,
re-rendert; `Cancel` verwirft und zeigt wieder das HTML.

## Done when
- `app/pm.php` (GET-Pfad) liefert zusaetzlich `raw` mit dem Roh-
  Markdown zurueck (neben dem bisherigen `html`, `status`, `path`).
- `app/_share/js/plan_loader.js`:
  - Speichert nach jedem erfolgreichen Load `current_raw` und `current_path`.
  - Rendert nach dem Markdown-Body eine Toolbar
    `<div class="content-toolbar"><button class="btn-edit">Edit</button>
     <button class="btn-save" hidden>Save</button>
     <button class="btn-cancel" hidden>Cancel</button></div>`.
  - Edit-Button ist nur sichtbar, wenn `current_path` gesetzt ist und
    der Pfad nicht `/archive/` enthaelt.
  - `btn-edit`-Klick: ruft `speckig_editor.mount(article, current_raw,
    current_path)` und togglet Buttons (`btn-edit` weg, `btn-save` +
    `btn-cancel` sichtbar).
  - `btn-save`-Klick: `await speckig_editor.save(current_path)`, bei
    `ok:true` Re-Load via `load_plan_path(current_path, false)`. Bei
    Fehler eine `.toolbar-error`-Zeile zeigen.
  - `btn-cancel`-Klick: `speckig_editor.destroy()`, Re-Load.
- Drei winzige CSS-Regeln in `app.css`: Toolbar-Layout (Flex), Buttons
  rechtsbuendig, Error-Zeile rot.

## Verifikation
- `php -l app/pm.php app/plan.php app/info.php` ok.
- Server 8086.
- `curl -s 'http://127.0.0.1:8086/pm.php?path=pm/ideas/talk_about_code_base.md' | grep -c '"raw":'` → 1.
- `curl -s 'http://127.0.0.1:8086/plan.php' | grep -c 'content-toolbar\|btn-edit'` ≥ 1 (Markup vorhanden bzw. Klassen referenziert — JS rendert sie zur Laufzeit, also pruefen wir den JS-Quellcode oder das Markup je nach Implementierung).
- Manual Browser-Smoketest:
  1. `/plan.php?path=pm/milestones/archive/011-ideas-and-info-tab/milestone.md` → Edit-Button **nicht** sichtbar (archive).
  2. `/info.php`, auf eine Idea klicken → Edit-Button sichtbar.
  3. Edit klicken, Text aendern, Save → Inhalt im Panel zeigt die
     Aenderung; `cat`-Check auf der Datei matcht.
  4. Edit, Aendern, Cancel → Aenderung weg.
  5. Bei Save-Fehler (z.B. Pfad manipuliert) erscheint `.toolbar-error`.

## Out of scope
- Buttons in `index.php`/`file.php` (M013).
- Mehrere offene Buffer / Tabs.
- Confirm bei Cancel mit ungespeicherten Aenderungen.
- Keyboard-Shortcut (Ctrl-S).

## Done
- `app/pm.php`: GET-Antwort enthaelt jetzt das Feld `"raw"` mit dem
  rohen Markdown (das bereits geladene `$raw_file_contents`). POST-Pfad
  unveraendert. Spec-Block oben um `raw` in der Response-Form
  erweitert.
- `app/_share/js/plan_loader.js` (massiv erweitert um den Edit-Flow):
  - Spec-Block oben dokumentiert Toolbar, `current_raw`/`current_path`,
    Archive-Guard und Mount via `speckig_editor`.
  - Modul-State: `current_raw`, `current_path`, `edit_is_active`.
  - In `load_plan_path()` werden `current_raw` (aus `data.raw`) und
    `current_path` (aus `data.path`) nach erfolgreichem Fetch und
    VOR dem DOM-Update gesetzt; `edit_is_active` wird auf false
    zurueckgesetzt, jeder Re-Load verlaesst implizit den Edit-Modus.
  - Nach dem Markdown-Body wird `render_toolbar(article_element)`
    gerufen. Die Toolbar erscheint nur, wenn `current_path` nicht leer
    ist UND nicht `/archive/` enthaelt — sonst wird sie nicht
    gerendert (kein leerer Container).
  - Toolbar-Klassen: `.content-toolbar`, `.btn-edit`, `.btn-save`,
    `.btn-cancel`. Save/Cancel starten `hidden`.
  - `on_edit_click()`: raeumt `.plan-markdown`, `.plan-status-header`
    und eine eventuelle `.toolbar-error`; haengt ein leeres
    `<div class="editor-mount">` VOR der Toolbar ein und ruft
    `window.speckig_editor.mount(mount_div, current_raw, current_path)`.
    Setzt `edit_is_active = true` und togglet Buttons.
  - `on_save_click()`: ruft `await window.speckig_editor.save(current_path)`.
    Bei `ok:true` `destroy()` + `load_plan_path(current_path, false)`.
    Bei Fehler `.toolbar-error`-Zeile direkt ueber der Toolbar; Editor
    bleibt offen, Puffer bleibt erhalten. Falls der Server eine
    `message` mitschickt, wird die angezeigt; sonst
    "Speichern fehlgeschlagen.".
  - `on_cancel_click()`: `speckig_editor.destroy()` + Re-Load.
  - `toggle_toolbar_buttons()` befragt das DOM nicht nach Sichtbarkeit,
    sondern liest `edit_is_active` und setzt `hidden`-Flags
    deterministisch.
  - `show_initial_placeholder()` und `show_invalid_path_message()`
    setzen `current_raw`/`current_path`/`edit_is_active` zurueck —
    ohne geladenen Pfad gibt es keinen sinnvollen Save-Target.
  - Defensive Guards: `speckig_editor` wird vor jedem Aufruf auf
    Verfuegbarkeit + Funktions-Typ geprueft (warnt via console.warn).
- `app/_share/css/app.css`: drei Mini-Regeln am Ende —
  `.content-toolbar` (flex, gap, justify-flex-end, margin-top),
  `.toolbar-error` (rot, margin-top) und `.editor-mount` (margin-top).
- Stil: BSD-Klammern auf eigener Zeile, snake_case, eine Bedingung pro
  Zeile mit sprechenden Bool-Namen
  (`path_is_archive`, `path_is_present`, `edit_is_allowed`,
  `editor_is_available`, `save_ok`, `toolbar_is_present`,
  `toolbar_is_complete`, `editor_can_be_destroyed`, ...).

### Verifikation
- `php -l app/pm.php app/plan.php app/info.php` ohne Fehler.
- `node --check app/_share/js/plan_loader.js` ohne Fehler (Node v22).
- `php -S 127.0.0.1:8086 -t app` als Smoke-Server gestartet (8083 nicht
  angefasst).
- `"raw"`-Feld:
  - `curl 'http://127.0.0.1:8086/pm.php?path=pm/ideas/talk_about_code_base.md'`
    -> grep `"raw":` = 1.
  - `curl 'http://127.0.0.1:8086/pm.php?path=pm/milestones/012-edit-plan-cm/milestone.md'`
    -> grep `"raw":` = 1.
- Markup-Hinweise im JS:
  - `grep -c content-toolbar app/_share/js/plan_loader.js` = 4.
  - `grep -c 'btn-edit\|btn-save\|btn-cancel' app/_share/js/plan_loader.js` = 6.
  - `grep -c /archive/ app/_share/js/plan_loader.js` = 3.
  - `grep -c speckig_editor app/_share/js/plan_loader.js` = 17.
- CSS-Regeln: `grep -c '.content-toolbar\|.toolbar-error\|.editor-mount'
  app/_share/css/app.css` = 3.
- Save end-to-end mit Backup + Restore:
  - `cp pm/ideas/talk_about_code_base.md /tmp/restore.bak`.
  - `curl -X POST --data 'roundtrip-test' '...action=save&path=pm/ideas/talk_about_code_base.md'`
    -> `{"ok":true,"path":"pm/ideas/talk_about_code_base.md","bytes":14}`.
  - GET re-fetch -> grep `"raw":"roundtrip-test"` = 1.
  - Restore aus `/tmp/restore.bak`, `git diff` leer.
- Archive-Pfad (Verifikation aus 0002 noch gruen):
  - GET `pm/milestones/archive/011-ideas-and-info-tab/milestone.md`
    -> grep `"ok":true` = 1.
  - POST gleicher Pfad -> grep `"ok":false` = 1.
- HTTP 200 fuer `/plan.php`, `/info.php`, `/index.php`.
- Streu-File-Check sauber: nur kanonisches `./app.sqlite`, keine
  `*.tmp.*`-Files in `pm/`.
- Server 8086 nach den Tests via TaskStop sauber beendet; 8083
  nicht angefasst.

Files touched:
- `app/pm.php` (+10 / -1).
- `app/_share/js/plan_loader.js` (+232 / -3).
- `app/_share/css/app.css` (+19 / -0).
- `pm/milestones/012-edit-plan-cm/milestone.md` (Haekchen + Pfad).
- ticket selbst nach `archive/`.
