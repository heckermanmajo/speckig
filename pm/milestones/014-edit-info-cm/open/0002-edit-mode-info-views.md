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
