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

## Plan
- **Server-Endpoint**: `app/pm.php` bekommt `POST ?action=new_report`.
  - Body: JSON `{ "slug": "...", "type": "research|audit|comparison" }`.
  - Slug: gleiches Regex wie 0003 (`[a-z0-9][a-z0-9-]*`, ≤ 80).
  - Type-Whitelist: `["research", "audit", "comparison"]`. Sonst 400.
  - **Naechste Nummer ermitteln**: `scandir("pm/reports")`, alle
    `NNNN-*.md`-Eintraege matchen, max+1, zero-padded auf 4. Bei
    leer → 1. Analog `new_ticket`-Numbering.
  - Zielpfad: `pm/reports/<NNNN>-<slug>.md`. Bei Kollision → 409
    (sollte durch max+1 nicht passieren, aber defensive Schicht).
  - Template fest verdrahtet (Begruendung wie in 0003):
    ```
    # NNNN — <slug-titlecased>

    Date: <today YYYY-MM-DD>
    Type: <type>
    Status: draft

    ## TL;DR

    ## Findings

    ## Sources

    ## Hooks for us
    ```
  - Datum via `date("Y-m-d")`.
  - Schreiben atomar via tmp+rename.
  - Antwort: `{ok:true, path, number:NNNN}`.
- **Sidebar-UI** in `info.php`: Block in `pm/reports`-Details mit
  Button `.btn-new-report` und Form `.new-report-form` (Slug-Input +
  Type-Select).
- **Sidebar-JS** in `plan_loader.js`: analog 0003, Endpoint
  `?action=new_report`.
- **Files touched**: `app/pm.php`, `app/info.php`,
  `app/_share/js/plan_loader.js`.

## Verifikation
- `php -l app/pm.php app/info.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf `info.php`:
  - Reports-Sektion → Button "+ Report" sichtbar.
  - Submit Slug `plan-smoke` / Type `research` → Datei
    `pm/reports/<NNNN>-plan-smoke.md` mit korrekter Numerierung
    (max+1 inklusive existierender Files).
  - Datei oeffnet im Edit-Mode mit Template-Inhalt; `Date:` ist heute,
    `Type:` ist `research`, `Status:` ist `draft`.
  - Cleanup: `rm pm/reports/<NNNN>-plan-smoke.md`.
- Reject: Type `unknown` → 400.
- Reject: Slug mit Sonderzeichen → 400.
- Vor-Pruefung: `ls pm/reports/` zeigt z.B. 0001-0007 → neuer Report
  bekommt 0008.
- `git status` clean, keine `*.tmp.*`.

## Out of scope (Plan)
- Final-Status setzen via UI.
- Manuelles Setzen der Nummer.
