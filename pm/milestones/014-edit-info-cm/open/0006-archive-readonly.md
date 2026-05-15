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

## Plan
- **Helper**: `app/_share/app.php` bekommt
  `app::is_archive_path($rel_path): bool`. Regel:
  - `true` wenn `preg_match('#(^|/)pm/[^/]+/.*/archive(/|$)#', $rel)`
    matched (Tickets in milestone-internem archive).
  - `true` wenn `str_starts_with($rel, "pm/milestones/archive/")`.
  - `true` wenn `str_starts_with($rel, "pm/bugs/archive/")`.
  - sonst `false`.
  - Bewusst: matched **nicht** `pm/decisions/0001-archive.md` etc.,
    weil das Segment `archive` als Verzeichnis-Bestandteil verlangt
    wird.
- **Save-Handler `pm.php`**: ersetzt die heutige Inline-Pruefung
  `str_contains($path, "/archive/")` durch
  `app::is_archive_path($path)`. Verhalten identisch oder strenger.
  Auch fuer `new_idea`/`new_report`/`new_decision`-Endpoints (aus
  0003/0004/0005) als Guard vor Schreiben einbauen — Schreibziele
  liegen nie unter `archive/`.
- **Save-Handler `file.php`**: existiert (M013/0001). Analoge
  Umstellung auf `app::is_archive_path()`.
- **`new_file`/`delete_file` in `file.php`**: ebenfalls absichern, dass
  weder Anlage noch Loeschung in `archive/` moeglich ist.
- **UI-Layer in `plan_loader.js`**: `path_is_archive`-Check existiert
  schon (Zeile 244) — Format mit `indexOf("/archive/")` ist
  ausreichend grob, aber harmlos (faengt mehr als noetig); kann
  bleiben. Spec-Kommentar ergaenzen, dass die Server-Schicht die
  Wahrheit ist.
- **Spec-Bloecke** in `app.php`, `pm.php`, `file.php` ergaenzen mit
  Verweis auf diese Decision.
- **Files touched**: `app/_share/app.php`, `app/pm.php`,
  `app/file.php`, evtl. `app/_share/js/plan_loader.js` (nur Kommentar).

## Verifikation
- `php -l` clean fuer alle drei PHP-Files.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- POST `pm.php?action=save&path=pm/ideas/archive/foo.md` → 400
  (synthetischer Pfad; muss schon vor jeder Filesystem-Aktion abgelehnt
  werden).
- POST `pm.php?action=save&path=pm/milestones/archive/013-edit-files-cm/milestone.md` →
  400 (Datei existiert! Vorher `cp` zur Sicherheit; nach Test `git diff`
  pruefen → leer).
- POST `pm.php?action=new_ticket` mit milestone_slug
  `archive/013-edit-files-cm` → 400 (bestehender new_ticket-Guard
  greift; pruefen ob hier ueberhaupt erreichbar).
- POST `file.php?action=save&path=pm/milestones/archive/013-edit-files-cm/archive/0001-save-file-endpoint.md`
  → 400; `git diff` leer.
- POST `file.php?action=delete_file&path=pm/milestones/archive/...` →
  400; Datei existiert weiter.
- Browser: alle archivierten Tickets zeigen weiterhin **keinen**
  Edit-Button (UI-Layer).
- Lese-Anzeige (`GET pm.php?path=pm/milestones/archive/...`) bleibt
  funktional.
- `git status` clean.

## Out of scope (Plan)
- Refactoring der `pm.php`-Save-Logik darueber hinaus.
- chmod / dateisystem-seitige Garantien.
