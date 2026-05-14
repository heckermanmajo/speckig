# 004 — Spec als Kommentar (Pilot)

Goal: Zwei Pilot-Dateien (`User.php`, `CreateUserAction.php`) tragen ihre Spec als Block-Kommentar im Code, die alten `.spec`-Dateien daneben sind geloescht. Liefert das lebende Vorbild fuer Decision 0006 und die Idee in `pm/ideas/spec-as-comment.md`. Format-Migration der restlichen Dateien und der PHP-Parser sind eigene spaetere Milestones.

Status: done

## Tickets
- [x] archive/0001-pilot-user-and-createuser.md

## Out of scope
- Restliche `.spec`-Dateien (eigener Milestone nach Pilot-Review).
- PHP-Parser fuer die UI (eigener Milestone, siehe Idea Schritt 4).
- `pm/how-to/spec.md` umschreiben — passiert im Format-Migrations-Milestone, nicht im Pilot.
- Decision 0005 superseden — passiert ebenfalls erst im Format-Milestone, nicht im Pilot.
- `Value.spec` im Repo-Root anfassen.

See: pm/decisions/0006-spec-parser.md, pm/ideas/spec-as-comment.md
