# 014 — Edit fuer Ideas / Reports / Decisions / Audits / Terms

Goal: Die in M011 read-only sichtbaren Info-Sektionen (Ideas, Reports,
Decisions, Audits, Terms) werden editierbar — CodeMirror-Editor analog
zu M012/M013, plus Aktionen "neue Idea anlegen", "neuen Report anlegen"
etc. Wichtig: Decisions bleiben **append-only** (siehe Decision-How-to);
die Action ist "neue Decision-Datei anlegen", nicht "alte Decision
editieren". Archivierte Tickets bleiben ebenfalls read-only.

Status: planned

## Tickets
- [x] archive/0001-save-info-endpoint.md — schreibender Endpoint fuer
      pm/ideas/*, pm/reports/*, pm/audits/*, pm/terms/*. Pfad-
      Whitelist; pm/decisions/* nur create, nie overwrite.
- [x] archive/0002-edit-mode-info-views.md — Edit-Button in der Plan-View
      fuer Info-Files; CodeMirror-Mount, Save/Cancel.
- [ ] open/0003-new-idea-action.md — Action "neue Idea anlegen", Slug-
      Eingabe, leere Datei aus Template (pm/how-to/ideas.md).
- [ ] open/0004-new-report-action.md — Action "neuer Report", naechste
      globale Nummer ermitteln, Template aus pm/how-to/reports.md.
- [ ] open/0005-new-decision-action.md — Action "neue Decision-Datei",
      naechste globale Nummer, Template aus pm/how-to/decisions.md.
      Edit bestehender Decisions bleibt verboten (Supersede via neue
      Datei).
- [ ] open/0006-archive-readonly.md — Hard-Guard: keine Schreib-
      Operation auf `pm/**/archive/` oder
      `pm/milestones/archive/**`. UI versteckt Edit-Button dort.

## Out of scope
- Edit von Audits-Inhalten ist erlaubt; Edit von Audit-*Reports* als
  Findings-Liste sehr wohl, aber kein Audit-Run aus dem UI.
- Search/Filter ueber die Info-Sektionen.
- Versionierung / Diff-Anzeige.
- Bot-Aktionen (`@bot` in Files anhaengen via UI) — separater
  Milestone wenn ueberhaupt.

See: pm/how-to/decisions.md, pm/how-to/ideas.md, pm/how-to/reports.md
