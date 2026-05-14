# 011 — Ideas + Info-Tab in der Plan-View

Goal: Die Plan-View zeigt nicht nur Milestones und Bugs, sondern auch
Ideas, Reports, Decisions, Audits und Terms — also alles, was nicht
direkter Milestone-Plan ist. Eintrittspunkt bleibt der `Plan`-Tab im
Header. Linke Sidebar erhaelt einen neuen Abschnitt "Info" (oder
"Ideas / Reports / Decisions / Audits / Terms" als ausklappbare
Sektionen), rechts wird der Markdown-Inhalt analog zur Milestone- und
Ticket-Render-Logik aus M006/0003 dargestellt. Read-only — kein Edit
(das macht M014).

Status: planned

## Tickets
- [x] archive/0001-info-sections-in-sidebar.md — pm_reader::list_info_sections,
      Sidebar-Block in plan.php (Klassen-Wiederverwendung, keine
      JS-/CSS-Aenderung).
- [ ] open/0002-plan-sidebar-info-sections.md — Sidebar in plan.php um
      die neuen Sektionen erweitern, je eigene `<details>`-Bloecke.
- [ ] open/0003-plan-loader-info-paths.md — plan_loader.js akzeptiert
      die neuen Pfade (pm/ideas/*, pm/reports/*, pm/decisions/*,
      pm/audits/*, pm/terms/*) und rendert sie als Markdown.

## Out of scope
- Inline-Edit / Neue Files anlegen via UI — eigener Milestone (M014).
- CodeMirror — eigener Milestone (M012 fuer Plan, M013 fuer Files,
  M014 fuer Info).
- Reihenfolge / Sortierung anders als pm_reader's Default.
- Suche / Filter ueber die neuen Sektionen.

See: pm/ideas/milestone und ticket view.md
