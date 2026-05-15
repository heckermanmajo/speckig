# 0001 — Save-Endpoint fuer Info-Files (Ideas / Reports / Audits / Terms)

## Goal
Inhalte unter `pm/ideas/`, `pm/reports/`, `pm/audits/`, `pm/terms/` lassen
sich aus dem UI speichern. `pm/decisions/` darf nur **neu angelegt**, nie
ueberschrieben werden.

## Notes
- Decisions sind append-only — Supersede via neue Datei, nie Edit
  (siehe `pm/how-to/decisions.md`). Der Endpoint muss das hart
  durchsetzen, nicht nur das UI.
- Pfad-Whitelist wirklich als Whitelist bauen, nicht als Blacklist:
  nur `pm/ideas/`, `pm/reports/`, `pm/audits/`, `pm/terms/` schreibbar;
  `pm/decisions/` nur create wenn der Zielpfad noch nicht existiert.
- Archivierte Tickets / Milestones (`pm/**/archive/`) bleiben
  read-only — kein Schreiben dort, auch wenn die Section es waere.
- Wiederverwendung der Save-Logik aus `app/pm.php` / `app/file.php` ist
  ok, aber keine stille Erweiterung deren Whitelists.
- Body-Limit, Binary-Guard, atomic-write wie in M012/0002 und
  M013/0001.

## Done when
- POST gegen einen Pfad in `pm/ideas/*.md`, `pm/reports/*.md`,
  `pm/audits/*.md`, `pm/terms/*.md` schreibt und liefert
  `{ok:true,path,bytes}`.
- POST gegen einen **neuen** Pfad in `pm/decisions/` schreibt einmal,
  ein zweiter POST auf denselben Pfad wird mit 400/409 abgelehnt.
- POST gegen Pfade ausserhalb der Whitelist (z.B. `pm/how-to/foo.md`,
  `pm/milestones/...`, irgendwas in `archive/`) wird mit 400 abgelehnt.
- Spec-Block am Endpoint dokumentiert Whitelist + Decision-Sonderregel.

## Out of scope
- UI-Wiring (eigenes Ticket 0002).
- "Neue Idea anlegen" / "neuer Report" / "neue Decision" — eigene
  Tickets 0003 / 0004 / 0005.
- Archiv-Schutz (eigenes Ticket 0006 — der Endpoint hier verhindert
  Schreiben ins Archiv schon implizit ueber die Whitelist; das Ticket
  0006 macht es zur expliziten Schicht).

See: pm/how-to/decisions.md, pm/how-to/ideas.md, pm/how-to/reports.md
