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

## Done
- Neuer Helper `app::is_archive_path(string $rel_path): bool` in
  `app/_share/app.php`. Drei Schichten (Doppelschicht mit Absicht):
    * Regex `^pm/[^/]+/(.*/)?archive(/|$)` — matched genau dann, wenn
      `archive` als Verzeichnis-Segment unterhalb von `pm/<x>/` steht.
      Faengt sowohl `pm/ideas/archive/foo.md` als auch
      `pm/milestones/<aktiv>/archive/<ticket>.md` UND
      `pm/milestones/archive/<ms>/...`.
    * `str_starts_with($rel, "pm/milestones/archive/")` — defensiv-
      redundant zur Regex, bleibt als zweite Schicht.
    * `str_starts_with($rel, "pm/bugs/archive/")` — ebenfalls
      defensiv-redundant.
  Anti-Match bewusst: `pm/decisions/0001-archive.md` und Konsorten
  mit `archive` als Bestandteil eines Dateinamens werden NICHT als
  Archiv-Pfad gewertet.
- `app/pm.php` Save-Handler: ersetzt die heutige Inline-
  `str_contains($path, "/archive/")`-Pruefung durch
  `app::is_archive_path($path)`. Verhalten ist strikt strenger,
  weil der Helper auf Pfade ausserhalb `pm/` keine Treffer mehr
  liefert — was hier ohnehin durch den vorgelagerten
  `pm/`-Prefix-Check abgedeckt ist.
- `app/pm.php` `new_idea`/`new_report`/`new_decision`/`new_ticket`-
  Handler: ergaenzen je einen `app::is_archive_path()`-Guard vor
  dem Schreiben. Ueber die fest verdrahteten Whitelist-Prefixe
  (`pm/ideas/`, `pm/reports/`, `pm/decisions/`, `pm/milestones/<ms>/open/`)
  und die Slug-Regexes ist das nicht direkt triggerbar — der
  Guard sitzt als defensive Schicht, damit Aenderungen an den
  Whitelists nie das Archive-Read-only-Versprechen brechen koennen.
  `new_milestone` braucht keinen Guard, weil der Endpoint pro
  Definition nur AKTIVE Milestones unter `pm/milestones/<slug>/`
  anlegt; `pm/milestones/archive/...` als Zielpfad ist syntaktisch
  unmoeglich (kein milestone_slug der Form `archive`).
- `app/file.php` Save-, new_file- und delete_file-Handler bekommen
  je den `app::is_archive_path()`-Guard. Save lehnt archivierte
  Pfade mit 400 ab; new_file blockt das Anlegen UNTER `archive/`
  (zielpfad = `<dir>/<name>`); delete_file blockt das Loeschen
  archivierter Dateien.
- Spec-Bloecke in `app/_share/app.php` (Helper-Spec mit Regex und
  Anti-Match-Beispielen), `app/pm.php` (Header-@spec + Save-@spec)
  und `app/file.php` (Header-@spec mit "Archive-Guard"-Hinweisen
  fuer alle drei Endpoints + inline-@spec-Erweiterungen).
- `app/_share/js/plan_loader.js`: Spec-Kommentar an
  `render_toolbar()` ergaenzt, der dokumentiert, dass die
  groebere `indexOf("/archive/")`-Heuristik im Client harmlos ist,
  weil die Wahrheit in `app::is_archive_path()` und den
  Server-Endpoints sitzt. Kein JS-Verhalten geaendert.

Files touched:
- `app/_share/app.php` (+~70: is_archive_path-Helper + Spec-Block).
- `app/pm.php` (+~70 / -3: Header-Spec, Save-Helper-Aufruf, vier
  neue Archive-Guards in new_idea/new_report/new_decision/new_ticket).
- `app/file.php` (+~55: Header-@spec, drei neue Archive-Guards in
  Save/new_file/delete_file, je mit Spec-Kommentar).
- `app/_share/js/plan_loader.js` (+~10: Spec-Kommentar an
  render_toolbar(), kein Verhalten).
- `pm/milestones/014-edit-info-cm/milestone.md` (Haekchen +
  archive/-Pfad fuer 0006, Status: planned -> done).
- Ticket selbst nach `archive/`.
- Gesamter Milestone-Folder nach `pm/milestones/archive/014-edit-info-cm/`.

Smoketest-Belege (Server `SPECKIG_ROOT=$(pwd) php -S 127.0.0.1:8086 -t app`):
- `php -l app/_share/app.php app/pm.php app/file.php` -> alle clean.
- Helper-Probe via `php -r`:
    * `pm/milestones/archive/013-edit-files-cm/milestone.md` -> true
    * `pm/bugs/archive/0001-foo.md` -> true
    * `pm/milestones/015-setup-repair-tab/archive/0001-x.md` -> true
    * `pm/milestones/014-edit-info-cm/archive/0001-save-info-endpoint.md` -> true
    * `pm/ideas/archive/foo.md` -> true
    * `pm/ideas/foo.md` -> false
    * `pm/decisions/0001-archive.md` -> false (Anti-Match — `archive` als
      Dateinamen-Bestandteil, nicht als Verzeichnis)
    * `pm/how-to/process.md` -> false
    * `pm/audits/0001-archive-audit.md` -> false (Anti-Match)
    * `pm/reports/2024-archive-rotation.md` -> false (Anti-Match)
- pm.php save:
    * `pm/milestones/archive/013-edit-files-cm/milestone.md` -> 400
      `Ungueltiger Pfad.`; `git diff --quiet` auf File clean.
    * `pm/milestones/015-setup-repair-tab/archive/foo.md` -> 400.
    * `pm/bugs/archive/0001-foo.md` -> 400.
    * `pm/ideas/m014-0006-sanity.md` (neue Datei, Sanity-Check) ->
      200 + bytes:16, danach manueller `rm` cleanup.
- file.php:
    * save `pm/milestones/archive/013-edit-files-cm/archive/0001-save-file-endpoint.md`
      -> 400, `git diff --quiet` clean.
    * new_file `{"dir":"pm/milestones/archive/013-edit-files-cm","name":"new-poison.md"}`
      -> 400, Datei nicht angelegt.
    * delete_file `pm/milestones/archive/013-edit-files-cm/milestone.md`
      -> 400, Datei existiert weiter.
- Read-Anzeige weiter funktional:
    * GET `pm.php?path=pm/milestones/archive/013-edit-files-cm/milestone.md`
      -> 200 + HTML.
    * GET `file.php?path=pm/milestones/archive/013-edit-files-cm/archive/0001-save-file-endpoint.md`
      -> 200.
- Vorheriges Verhalten:
    * Decision overwrite `pm/decisions/0001-bootstrap.md` -> 409
      `Decision ist append-only.` (M014/0001 unveraendert).
    * Decision create `pm/decisions/9999-smoke-m014-0006.md` via
      save -> 200 + bytes:13 (Whitelist erlaubt brandneue Decisions).
      Cleanup ok.
    * new_idea `{"slug":"m014-0006-idea-smoke"}` -> 200; cleanup ok.
    * new_report `{"slug":"m014-0006-rep-smoke","type":"research"}`
      -> 200 + number 0003; cleanup ok.
    * new_decision `{"slug":"m014-0006-dec-smoke","supersedes":""}`
      -> 200 + number 0008; cleanup ok.
    * new_ticket `{"milestone_slug":"015-setup-repair-tab",...}` -> 200
      + Ticket-Pfad. Cleanup: rm + git-checkout milestone.md.
- Bonus-Smoketest nach Milestone-`git mv`: POST gegen
  `pm/milestones/archive/014-edit-info-cm/archive/0001-save-info-endpoint.md`
  -> 400 (jetzt unter `pm/milestones/archive/<m014>/...`; Helper
  matched korrekt).
- Streu-Files: `find . -name "*.tmp.*" -not -path "./.git/*"` leer;
  `find . -name "app.sqlite*"` zeigt nur `./app.sqlite`.
- Server am Ende via `TaskStop` gestoppt; Port 8083 nicht
  angefasst.
