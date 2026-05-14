# 006 — Plan-View (Milestones, Tickets, Bugs)

Goal: Eine zweite Hauptansicht erreichbar ueber `Plan`-Link im Header. Linker Bereich: Liste der Milestones (active oben, archived unten) und Bugs, jeweils aufklappbar mit Tickets darunter. Rechter Bereich: gerenderter Markdown-Inhalt der angeklickten Datei (`milestone.md` oder Ticket). Teilt sich Header und Layout-Skelett mit der Tree-View. Liefert die "Ueberblick statt Datei-Browse"-Sicht aus `pm/ideas/milestone und ticket view.md`.

Status: done

## Tickets
- [x] archive/0001-pm-reader-and-shared-header.md
- [x] archive/0002-plan-page-and-sidebar.md
- [x] archive/0003-ticket-and-milestone-render.md

## Out of scope
- Inline-Edit von Tickets / Milestones (eigener spaeterer Milestone, falls gewollt).
- Status-Aenderungen via UI (Box haekchen, Ticket archivieren, Milestone schliessen).
- Commits-View (Diff + Zusammenfassung + Groesse) — zweite Idee aus `pm/ideas/milestone und ticket view.md`, eigener spaeterer Milestone (M007 vermutlich).
- Suche / Filter ueber Tickets.
- Drag-and-Drop / Boards / Kanban.
- Bot-Aktionen (`@bot do X`) — siehe `pm/how-to/at-bot.md`, separat.
- JSON-Spec-Parser-Anbindung an Tickets — Tickets sind Markdown, fertig.

See: pm/ideas/milestone und ticket view.md
