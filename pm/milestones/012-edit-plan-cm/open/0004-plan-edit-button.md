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
