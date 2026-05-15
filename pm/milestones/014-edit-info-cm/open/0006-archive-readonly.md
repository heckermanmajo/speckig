# 0006 — Hard-Guard: Archiv ist read-only

## Goal
Nichts unter `pm/**/archive/` und `pm/milestones/archive/**` darf je
durch das UI veraendert werden — weder Edit noch Neuanlage. Der Guard
sitzt im Server, das UI versteckt zusaetzlich den Edit-Button.

## Notes
- Server-Schicht ist die Wahrheit; UI-Versteck ist nur Komfort. Wer
  curl benutzt, bekommt vom Server eine 400.
- Pattern darf nicht versehentlich `pm/decisions/foo-archive.md` o.ae.
  treffen — exakter Pfad-Match auf das Segment `archive` als
  Verzeichnis-Bestandteil.
- Tickets unter `pm/milestones/<aktiv>/archive/` sind genauso geschuetzt
  wie `pm/milestones/archive/<milestone>/...`. Beide Faelle abdecken.
- Endpoint aus 0001 (Info-Save) und Endpoints aus M012/M013
  (`pm/save`, `file save`) muessen das alle wissen — wenn moeglich
  eine gemeinsame `app::is_archive_path()`-Helfer einfuehren, statt
  drei Mal kopieren.
- Archive-Files lesen muss weiter erlaubt sein.

## Done when
- POST gegen einen Pfad in `pm/**/archive/...` → 400, Datei unveraendert.
- POST gegen `pm/milestones/archive/<x>/...` → 400, Datei unveraendert.
- Plan/Info-Views fuer Files im Archiv zeigen keinen Edit-Button.
- "Neue Idea/Report/Decision"-Buttons sind in Archiv-Listen nicht
  sichtbar (falls die UI dort welche listet).
- Lese-Anzeige archivierter Files funktioniert weiter.

## Out of scope
- Schutz vor manuellen Dateisystem-Aktionen (chmod, direkter Editor) —
  nur das UI/Server-Pfad.
- Diff zwischen Archiv- und aktiver Version.
- Unarchive-Action.

See: pm/how-to/process.md
