# 013 — Edit in der Tree-View (Files) mit CodeMirror

Goal: In der Tree-View (`index.php`, `file.php`-Renderer) laesst sich
die rechts angezeigte Datei ueber einen `Edit`-Button in einen
CodeMirror-Editor schalten, bearbeiten und speichern. Speichern geht
gegen einen schmalen schreibenden Endpoint mit Pfad-Validierung
analog zu M012, aber mit weiterem Scope (nicht nur pm/, sondern alles
unter SPECKIG_ROOT). Plus: Action "neue Datei anlegen" im aktuellen
Verzeichnis, "Datei loeschen" (mit Confirm), Mode-Auswahl je
Extension (CodeMirror modes nach Decision 0002 / 0006).

Status: planned

## Tickets
- [ ] open/0001-save-file-endpoint.md — schreibender Endpoint mit
      SPECKIG_ROOT-Scope, atomar, Mime-/Binary-Guard.
- [ ] open/0002-edit-mode-tree-view.md — Edit-Button + CodeMirror-Mount
      im rechten Panel der Tree-View, Save/Cancel, Re-Render via
      content_loader.
- [ ] open/0003-cm-mode-per-extension.md — CodeMirror-Mode-Auswahl
      anhand Extension (.php, .js, .ts, .md, .lua, .nim, .groovy).
- [ ] open/0004-new-file-action.md — Action "neue Datei im aktuellen
      Verzeichnis", Formular fuer Dateinamen, leere Datei anlegen.
- [ ] open/0005-delete-file-action.md — Action "Datei loeschen" mit
      Confirm-Prompt, kein rm -r, keine Verzeichnisse.

## Out of scope
- Rename / Move (eigenes spaeteres Ticket falls gewollt).
- Mehrfach-Auswahl, Bulk-Aktionen.
- Versionierung / git-Operationen aus dem UI.
- Edit binaerer Dateien.
- Edit von Specs separat vom Code (Spec lebt im selben File-Stream).
