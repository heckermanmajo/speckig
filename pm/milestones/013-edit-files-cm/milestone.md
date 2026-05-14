# 013 — Edit in der Tree-View (Files) mit CodeMirror

Goal: In der Tree-View (`index.php`, `file.php`-Renderer) laesst sich
die im Code-Tab angezeigte Datei ueber einen `Edit`-Button in einen
CodeMirror-Editor schalten, bearbeiten und speichern. Das `<pre>` im
Code-Tab wird in-place gegen den Editor getauscht; `Save` schickt an
einen neuen Endpoint `POST /file.php?action=save&path=...` mit
SPECKIG_ROOT-Scope (alles unter dem Repo-Root ausser Vendor und
Binaries); `Cancel` restauriert das `<pre>`. Der Spec-Tab bleibt
unangetastet. Plus: "+ Datei"-Aktion im aktuellen Verzeichnis,
"Datei loeschen" mit Confirm.

Status: planned

## Tickets
- [x] archive/0001-save-file-endpoint.md — `app/file.php` um POST
      `?action=save` erweitern; SPECKIG_ROOT-Scope; Schreib atomar via
      tmp+rename; Schwarzliste fuer `app/_share/vendor/`, `.git/`,
      `app/_share/spec_parser/`; Binary-Guard via Byte-Check; 1 MB
      Body-Limit.
- [x] archive/0002-cm-mode-vendoring.md — Sieben weitere CodeMirror-Mode-
      Files vendoren (`php`, `javascript`, `clike`, `shell`, `css`,
      `xml`, `yaml`) und Extension→Mode-Mapping als JS-Helper in
      `editor.js` ergaenzen. Decision 0007 ergaenzen.
- [ ] open/0003-edit-mode-tree-view.md — Edit/Save/Cancel-Toolbar im
      Code-Tab; `<pre>` wird beim Edit gegen CodeMirror-Mount getauscht;
      Mode aus Extension. Toolbar nur sichtbar, wenn der Pfad nicht
      in der Schwarzliste liegt (`raw` im JSON gibt's neu).
- [ ] open/0004-new-file-action.md — Action "+ Datei" pro Tree-Ordner:
      Inline-Form (Filename), POST `?action=new_file&dir=...`, legt
      leere Datei an, Tree reloaded.
- [ ] open/0005-delete-file-action.md — "Datei loeschen"-Button neben
      dem Edit-Button mit JS-`confirm()`-Prompt; POST
      `?action=delete_file&path=...`; nur Files, keine Verzeichnisse;
      Schwarzliste greift auch hier.

## Out of scope
- Rename / Move (eigenes spaeteres Ticket falls gewollt).
- Mehrfach-Auswahl, Bulk-Aktionen.
- Versionierung / git-Operationen aus dem UI.
- Edit binaerer Dateien.
- Edit von Specs separat vom Code (Spec lebt im selben File-Stream).
- Mode-Auswahl manuell pro File (immer nach Extension).
- Per-File-Encoding-Detection (UTF-8 wird angenommen).

See: pm/decisions/0007-editor-vendoring.md, pm/reports/0002-editor-library.md
