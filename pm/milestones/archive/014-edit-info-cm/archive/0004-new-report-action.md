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

## Done
- `app/pm.php` um POST-Handler `?action=new_report` erweitert.
  - Body JSON `{slug, type}`. Slug-Whitelist `^[a-z0-9][a-z0-9-]*$`,
    Laenge 1-80. Type-Whitelist hart: `["research", "audit", "comparison"]`.
    Ungueltige Slugs/Types -> 400 + Log via `app::error_log()`.
  - Naechste Nummer GLOBAL: `scandir("pm/reports")`, alle `NNNN-*.md`-
    Eintraege gematcht, max+1. Bei leerem Verzeichnis -> 1. Wird
    `sprintf("%04d", ...)` zero-padded — analog zu `new_ticket`-
    Numerierung, aber ohne `archive/`-Scan (Reports haben kein archive/).
  - Zielpfad `pm/reports/NNNN-<slug>.md`. Defensive Collision-Pruefung
    -> 409 (sollte durch max+1 nie greifen, aber Schutz fuer Race-
    Verlieger).
  - Template fest verdrahtet via HEREDOC. Title aus Slug abgeleitet:
    `ucwords(str_replace("-", " ", $slug))` ("agent-smoke" ->
    "Agent Smoke"). Datum via `date("Y-m-d")`. Type aus Body, Status
    hart "draft". Sections `## TL;DR / ## Findings / ## Sources /
    ## Hooks for us` aus `pm/how-to/reports.md`.
  - Atomar geschrieben via `tmp + rename`. Tmp bei Fehler aufgeraeumt.
  - Antwort 200 + `{ok:true, path, number}`.
  - Spec-Block ueber dem Handler dokumentiert Vertrag + Numerierung;
    Datei-Header-Spec um den neuen Endpoint erweitert.
- `app/info.php`: zweiter Action-Block analog zum Ideas-Block in 0003,
  fuer die Reports-Sektion. Form enthaelt `.input-slug` (Slug-Input,
  maxlength 80) plus `.input-type` (Select mit Optionen research / audit /
  comparison). Klassen `.btn-new-report`, `.new-report-form`,
  `.btn-cancel-form`, `.form-error` — spiegeln das `.btn-new-idea`-
  Pattern, damit kein neues CSS noetig ist.
- `app/_share/js/plan_loader.js` um `init_new_report_form()` + die
  Handler `on_new_report_button_click`, `on_new_report_cancel_click`,
  `on_new_report_submit` erweitert. Submit POSTet `{slug, type}` an
  `/pm.php?action=new_report`; Cancel resettet Slug-Input und setzt
  Type-Select auf `selectedIndex = 0`. Bei `ok:true` navigiert auf
  `info.php?path=<data.path>` — der Server liefert den vollen Pfad
  inkl. NNNN-Prefix, Client baut ihn nicht selbst zusammen.
  Self-guarded (no-op ohne Button/Form im DOM, z.B. auf plan.php).
  Spec-Kommentar oben um den M014/0004-Abschnitt erweitert.

Files touched:
- `app/pm.php` (+~210 / -2: Header-Spec, action-Flag, Handler-Block
  mit Spec-Kommentar).
- `app/info.php` (+~25 / -1: zweiter Action-Block fuer Reports +
  Aufruf von render_info_section mit dem 4. Argument).
- `app/_share/js/plan_loader.js` (+~225 / -1: Spec-Block-Erweiterung,
  init_new_report_form-Funktionsfamilie, Aufruf in init_plan_loader).
- `pm/milestones/014-edit-info-cm/milestone.md` (Haekchen +
  archive/-Pfad fuer 0004).
- Ticket selbst nach `archive/`.

Smoketest-Belege (Server `php -S 127.0.0.1:8086 -t app`):
- `php -l app/pm.php app/info.php` -> clean.
- Pre-State `ls pm/reports/` -> 0001, 0002.
- Happy research `{"slug":"agent-smoke","type":"research"}` -> 200 +
  `{ok:true,path:"pm/reports/0003-agent-smoke.md",number:"0003"}`.
  `cat` zeigt Template mit `# 0003 — Agent Smoke`, `Date: 2026-05-15`,
  `Type: research`, `Status: draft`, alle vier Sections. Cleanup ok.
- Happy audit `{"slug":"agent-audit-smoke","type":"audit"}` -> 200 +
  0003 (Pre-State zurueck nach Cleanup). `Type: audit`. Cleanup ok.
- Happy comparison `{"slug":"agent-comparison-smoke","type":"comparison"}`
  -> 200 + 0003. `Type: comparison`. Cleanup ok.
- Reject bad type `{"slug":"x","type":"foo"}` -> 400 + `Ungueltiger type.`.
- Reject bad slug `{"slug":"Bad!","type":"research"}` -> 400 +
  `Ungueltiger slug.`.
- Reject empty `{}` -> 400 + `Ungueltiger slug.` (Slug-Check first).
- HTML `curl info.php | grep -c btn-new-report` -> 1.
- HTML `curl info.php | grep -c new-report-form` -> 1.
- JS `grep -n "new-report\|new_report" plan_loader.js` -> 31 Matches
  (Spec-Block + Selektoren + Submit-Body + init-Aufruf).
- Numerierung: zwei Reports hintereinander -> 0003, 0004. Beide cleanup.
- Regression M014/0003 `{"slug":"regr-smoke"}` an `?action=new_idea`
  -> 200 + `{ok:true,path:"pm/ideas/regr-smoke.md"}`. Cleanup ok.
- Streu-Files: `*.tmp.*` leer, `app.sqlite*` zeigt nur `./app.sqlite`.
- Server via `TaskStop` gestoppt; Port 8083 nicht angefasst.
