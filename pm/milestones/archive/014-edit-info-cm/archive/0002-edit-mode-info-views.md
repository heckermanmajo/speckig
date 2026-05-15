# 0002 — Edit-Mode in der Plan-/Info-View fuer Info-Files

## Goal
Die Read-only-Ansicht eines Info-Files (Idea, Report, Audit, Term) hat
einen Edit-Button, der den Inhalt in einen CodeMirror-Editor umlaedt;
Save schreibt zurueck via Endpoint aus 0001, Cancel verwirft.

## Notes
- Symmetrie zu M012/M013 ist das Ziel — gleicher Editor, gleiche
  Toolbar-Logik, gleiche Tastenkuerzel. Kein zweites Pattern erfinden.
- Decisions: Edit-Button hier **nicht** anbieten — Decisions sind
  append-only. Stattdessen wird das eigene Ticket 0005 die Action
  "neue Decision-Datei" liefern.
- Archivierte Info-Files (`pm/**/archive/`, `pm/milestones/archive/`)
  bekommen keinen Edit-Button (siehe 0006).
- Editor darf nicht doppelt mounten, wenn der User schnell zwischen
  Tabs klickt.
- Bestehende Render-Pfade (Markdown via Parsedown) duerfen sich nicht
  veraendern — der Edit-Mode ist eine Overlay-Variante, kein
  Replacement.

## Done when
- Eine Idea/Report/Audit/Term-Seite im UI zeigt einen Edit-Button.
- Klick → CodeMirror-Editor mit aktuellem Inhalt, Toolbar mit Save +
  Cancel.
- Save persistiert (via 0001-Endpoint) und kehrt in den Read-Mode mit
  neuem Inhalt zurueck.
- Cancel verwirft Aenderungen, kehrt in Read-Mode zurueck.
- Decisions-Seite zeigt keinen Edit-Button.
- Archiv-Seiten zeigen keinen Edit-Button.

## Out of scope
- Save-Endpoint selbst (0001).
- Neue-Datei-Aktionen (0003/0004/0005).
- Hard-Guard fuer Archiv (0006) — hier nur UI-seitig versteckt, der
  Server-Guard kommt separat.
- Diff-Anzeige / Versionierung.

See: pm/how-to/decisions.md

## Plan
- **Wo der Code lebt**: `plan_loader.js` rendert heute schon
  Edit/Save/Cancel-Toolbar fuer alles, was via `info.php` und
  `plan.php` reingeladen wird (`render_toolbar()`, Zeilen 242 ff.).
  `edit_is_allowed = path_is_present && ! path_is_archive` ist die
  einzige Guard-Schicht.
- **Anpassung**: Decision-Guard ergaenzen.
  - Neue Bedingung in `render_toolbar`:
    `let path_is_decision = current_path.indexOf("pm/decisions/") === 0;`
    `let edit_is_allowed = path_is_present && ! path_is_archive && ! path_is_decision;`
  - Decisions sind dann automatisch read-only im UI. Server-Seite
    blockt zusaetzlich (014/0001), aber die UI-Schicht spart den User
    den Klick.
- **Info-Sektionen** (Ideas, Reports, Audits, Terms) brauchen **kein**
  neues JS — sie laufen schon ueber `plan_loader.js` (siehe info.php
  Spec-Block). Der Edit-Button erscheint also automatisch, sobald
  014/0001 den Save-Endpoint akzeptiert.
- **Save-Path** in `editor.js` (`speckig_editor.save(current_path)`)
  zeigt auf `pm.php?action=save&path=...` — das passt zu 014/0001.
- **Archiv-Guard**: existiert schon via `path_is_archive` — keine
  zusaetzliche Arbeit fuer 0006 hier; 0006 macht die Server-Seite hart.
- **CSS**: keine Aenderung — Toolbar-Styles sind in `app.css` aus M012.
- **Files touched**: `app/_share/js/plan_loader.js` (Decision-Guard +
  Spec-Kommentar), nichts anderes.

## Verifikation
- `php -l app/info.php` clean (sollte trivial sein, info.php wird
  nicht angefasst).
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf `http://127.0.0.1:8086/info.php`:
  - Klick auf eine Idea-Datei → Edit-Button sichtbar.
  - Klick auf eine Decision-Datei → **kein** Edit-Button.
  - Klick auf einen Report → Edit-Button.
  - Klick auf einen Audit → Edit-Button.
  - Klick auf einen Term → Edit-Button.
- Edit auf Idea: Toolbar zeigt Save/Cancel, Save persistiert, Reload
  zeigt neuen Inhalt. Restore via `git checkout`.
- Cancel: Buffer verworfen, Read-Mode zurueck, Inhalt unveraendert.
- Plan-Tab Regression: Klick auf ein offenes Ticket → Edit-Button
  weiterhin sichtbar (Bestaetigung dass 0002 keine Plan-View
  beschaedigt).
- Archiv-Tab: Klick auf ein archiviertes Ticket → kein Edit-Button
  (unveraendert).
- `find . -name "*.tmp.*" -not -path "./.git/*"` leer.
- `git status` clean.

## Out of scope (Plan)
- Keine neuen CodeMirror-Modes (Markdown ist schon gevendored).
- Keine UI fuer "neue Decision" — eigenes Ticket 0005.

## Done
- `app/_share/js/plan_loader.js` `render_toolbar()` um Decision-Guard
  erweitert:
  - Neue Bedingung `let path_is_decision = current_path.indexOf("pm/decisions/") === 0;`
    direkt neben dem bestehenden `path_is_archive`.
  - `edit_is_allowed = path_is_present && ! path_is_archive && ! path_is_decision`.
  - Decisions sind damit UI-seitig read-only — die Toolbar wird gar
    nicht gerendert, statt nur den Edit-Button auszublenden (gleiches
    Muster wie `/archive/`).
- Spec-Kommentar oberhalb der `render_toolbar()`-Sektion um den
  M014/0002-Block erweitert: dokumentiert dass Decisions append-only
  sind, dass der Edit-Button bewusst nicht erscheint, dass eine neue
  Decision via 0005 kommt und dass die Server-Schicht (014/0001)
  zusaetzlich blockt.
- Info-Sektionen (Ideas, Reports, Audits, Terms) bekommen ohne
  weiteren Code automatisch den Edit-Button — sie laufen ueber
  denselben `plan_loader.js`/`render_toolbar()`-Pfad und liegen nicht
  unter `pm/decisions/`. Save schreibt via dem in 014/0001
  bereitgestellten pm.php-Endpoint zurueck.
- `path_is_archive` bleibt unveraendert — archivierte Tickets und
  archivierte Info-Files bleiben weiterhin read-only.

Files touched:
- `app/_share/js/plan_loader.js` (+10 / -2: Decision-Guard +
  Spec-Kommentar im `render_toolbar()`-Block).
- `pm/milestones/014-edit-info-cm/milestone.md` (Haekchen +
  archive/-Pfad fuer 0002).
- ticket selbst nach `archive/`.

Smoketest-Belege:
- `php -l app/info.php` -> clean (Datei nicht angefasst, Sanity-Check).
- `grep -n "path_is_decision" app/_share/js/plan_loader.js` ->
  Match in Z. 253 (`let path_is_decision = current_path.indexOf("pm/decisions/") === 0;`).
- `grep -n "edit_is_allowed" app/_share/js/plan_loader.js` -> Match
  in Z. 256 mit `&& ! path_is_decision`-Suffix; Guard greift in Z. 258.
- Server `php -S 127.0.0.1:8086 -t app`:
  - `GET /info.php` -> HTTP 200, HTML-Head mit `speckig — info`.
  - `GET /_share/js/plan_loader.js` -> liefert das aktualisierte
    Modul, beide grep-Marker (`path_is_decision`, `edit_is_allowed`)
    auch in der ausgelieferten Datei.
  - `GET /pm.php?path=pm/decisions/0001-bootstrap.md` -> 200 mit
    `"path":"pm/decisions/0001-bootstrap.md"` — der Loader bekommt
    den Praefix `pm/decisions/`, `indexOf === 0` greift, Toolbar wird
    gar nicht gerendert.
- Streu-File-Check: nur `./app.sqlite`; `*.tmp.*` leer.
- `git status` clean nach Move + milestone-Update.
