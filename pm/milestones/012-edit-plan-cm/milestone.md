# 012 — Edit in der Plan-View mit CodeMirror

Goal: In der Plan-View laesst sich die rechts angezeigte Datei
(milestone.md oder ein Ticket) ueber einen `Edit`-Button in einen
CodeMirror-Editor schalten, bearbeiten und speichern. Speichern geht
gegen einen schmalen `POST /pm.php?action=save&path=...`-Endpoint
(read-only-Vertrag von M006 wird ergaenzt um schreibenden Pfad mit
strenger pm/-Validierung). Aktionen "neuen Milestone anlegen", "neues
Ticket im Milestone anlegen", "Box im milestone.md abhaken / Ticket
archivieren" gehoeren ebenfalls hierhin — sie sind die *Plan-Schreib-*
Aktionen.

Status: planned

## Tickets
- [ ] open/0001-cm-vendor-and-spec.md — Decision-Update zu
      `pm/decisions/0002-editor-library.md` (oder Supersede), CodeMirror-
      Vendoring nach `app/_share/js/vendor/codemirror/`, Spec daneben.
- [ ] open/0002-save-endpoint.md — POST-Handler in `app/pm.php` fuer
      schreibendes Speichern; pm/-Pfade only; atomar (tmp + rename).
- [ ] open/0003-edit-mode-plan-view.md — Edit-Button im rechten Panel,
      CodeMirror-Mount, Save/Cancel; nach Save Re-Render via
      plan_loader.
- [ ] open/0004-new-milestone-action.md — Action "neuen Milestone
      anlegen": Formular fuer Slug + Titel, legt
      `pm/milestones/NNN-slug/milestone.md` + `open/` + `archive/` an.
- [ ] open/0005-new-ticket-action.md — Action "neues Ticket im
      Milestone anlegen": Slug + Titel, legt `NNNN-slug.md` in
      `open/` an und ergaenzt milestone.md.

## Out of scope
- Edit von Files in der Tree-View / Dateibaum (M013).
- Edit von Reports / Decisions / Ideas / Audits / Terms (M014).
- Status-Aenderung (Done-when-Box, archivieren) — eigenes Folge-Ticket.
- Konflikt-Aufloesung wenn jemand gleichzeitig editiert.
- Undo/Redo ausserhalb von CodeMirrors eingebautem History-Stack.
- Diff-Anzeige beim Speichern.

See: pm/decisions/0002-editor-library.md, pm/ideas/milestone und ticket view.md
