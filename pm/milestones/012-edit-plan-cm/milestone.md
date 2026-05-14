# 012 — Edit in der Plan-View mit CodeMirror

Goal: Markdown-Inhalte in der Plan-View (`milestone.md` und Tickets in
`open/` / `archive/`) lassen sich im Browser editieren. Rechts im
Content-Panel erscheint ein `Edit`-Button; ein Klick tauscht die
Markdown-Anzeige gegen einen CodeMirror-Editor mit Markdown-Mode.
`Save` schickt den Inhalt per `POST /pm.php?action=save&path=...` an
einen schmalen Schreib-Endpoint (pm/-Whitelist, atomar via
tmp+rename) und re-rendert die Anzeige; `Cancel` verwirft den
Buffer. Zusaetzlich gibt es zwei Erzeuger-Aktionen: "neuer Milestone"
und "neues Ticket in diesem Milestone".

Status: planned

## Tickets
- [x] archive/0001-cm-vendor.md — CodeMirror 5 vendoren
      (`app/_share/vendor/js/codemirror.min.js`, dazu `markdown`-Mode
      und `codemirror.css`), Originalheader behalten, Decision-File
      `pm/decisions/0007-editor-vendoring.md`.
- [x] archive/0002-save-endpoint.md — `app/pm.php` um POST-Pfad
      `?action=save&path=...` erweitern; Pfad muss mit `pm/` beginnen
      und auf `.md` enden; Schreib atomar via tmp + `rename()`;
      Antwort JSON `{ok, path}` bzw. `{ok:false, message}`.
- [x] archive/0003-editor-js.md — Neuer Layer `app/_share/js/editor.js`,
      IIFE im Stil von `plan_loader.js`: `editor::mount(article,
      raw_markdown, path)`, `editor::save(...)`, `editor::cancel(...)`.
      Mode = `markdown`, BSD-Klammern, snake_case.
- [ ] open/0004-plan-edit-button.md — Edit/Save/Cancel-Buttons im
      Content-Panel von `plan.php` (und automatisch auch in `info.php`,
      weil derselbe Loader laeuft), nur sichtbar wenn ein Pfad geladen
      ist und nicht unter `archive/`. Plan-Loader holt vor dem Render
      `raw`-Inhalt mit, damit `Edit` ohne Server-Roundtrip startet.
- [ ] open/0005-new-milestone-action.md — Action "neuer Milestone" oben
      in der Plan-Sidebar: Formular fuer Slug + Titel; legt
      `pm/milestones/NNN-slug/{milestone.md,open/.gitkeep,archive/.gitkeep}`
      an; naechste freie NNN wird automatisch bestimmt; Sidebar
      reloaded.
- [ ] open/0006-new-ticket-action.md — Action "neues Ticket in diesem
      Milestone" am Milestone-Block: Formular fuer Slug + Titel; legt
      `NNNN-slug.md` in `open/` an (mit Template aus
      `pm/how-to/milestones.md`) und ergaenzt die Bullet-Liste im
      `milestone.md` per Insert vor "## Out of scope".

## Out of scope
- Edit ausserhalb von `pm/` (M013).
- Edit von `pm/ideas`, `pm/reports`, `pm/decisions`, `pm/audits`,
  `pm/terms` (M014).
- Edit oder Box-Toggle in archivierten Milestones / Tickets — nur
  read-only.
- Eigener Box-Toggle-Endpoint — Haken wird im Edit-Modus per Text-
  Aenderung gesetzt.
- Archivieren-Action mit `git mv` aus dem UI — bewusst ausgelassen,
  bleibt CLI-Workflow.
- Konflikt-Aufloesung wenn zwei Sessions gleichzeitig editieren.
- Undo/Redo ausserhalb von CodeMirrors eingebautem History-Stack.
- Diff-Anzeige beim Speichern.

See: pm/decisions/0002-editor-library.md, pm/reports/0002-editor-library.md,
pm/ideas/milestone und ticket view.md
