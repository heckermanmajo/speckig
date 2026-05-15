# 0004 — Action "neuer Report"

## Goal
Aus dem UI laesst sich ein neuer Report anlegen: System ermittelt die
naechste globale Report-Nummer, legt `pm/reports/NNNN-<slug>.md` mit
Template-Inhalt an und oeffnet die Datei im Edit-Mode.

## Notes
- Nummerierung ist **global** und wird nie wiederverwendet (siehe
  `pm/how-to/reports.md`). Naechste Nummer = max(existierende) + 1,
  inklusive `archive/`-Eintraegen.
- Race zwischen zwei gleichzeitigen "Neuer Report"-Klicks ist
  unwahrscheinlich, aber der Code soll trotzdem nicht zwei gleiche
  Nummern produzieren — atomar genug, dass es im UI nicht aufschlaegt.
- Template kommt aus dem Block in `pm/how-to/reports.md` (TL;DR /
  Findings / Sources / Hooks for us). Date auf heute setzen, Status
  `draft`.
- Slug-Regeln wie in 0003.
- Action gehoert in die Info-Sektion fuer Reports.

## Done when
- Button "Neuer Report" sichtbar in der Reports-Info-Sektion.
- Klick → Inline-Form mit Slug + Type (research/audit/comparison).
- Submit → Datei `pm/reports/NNNN-<slug>.md` existiert mit Template,
  korrekter naechster Nummer, heutigem Datum, gewaehltem Type, Status
  `draft`. UI springt in den Edit-Mode der neuen Datei.
- Submit mit ungueltigem Slug → Fehler, keine Datei.
- Existieren bereits Reports 0001-0007, vergibt die Action 0008.

## Out of scope
- Final-Status setzen.
- Loeschen / Verschieben.
- Audit-Run aus dem UI.

See: pm/how-to/reports.md
