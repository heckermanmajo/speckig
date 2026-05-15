# 0005 — Action "neue Decision-Datei"

## Goal
Aus dem UI laesst sich eine neue Decision anlegen: System ermittelt die
naechste globale Decision-Nummer, legt `pm/decisions/NNNN-<slug>.md` mit
Template an, oeffnet im Edit-Mode. Bestehende Decisions bleiben
read-only — Supersede laeuft ueber "neue Decision", nicht ueber Edit.

## Notes
- Decisions sind **append-only**. Diese Regel ist im Save-Endpoint
  (0001) schon serverseitig durchgesetzt; die Action hier ist die
  einzige legale Quelle fuer neue Decisions im UI.
- Wenn die neue Decision eine alte ersetzt, gehoert die Zeile
  `Supersedes: NNNN-<slug>` ins Template — bequemer Eingabe-Slot in
  der Form vorsehen.
- Nummerierung global, max+1 inklusive existierender Files.
- Template-Form steht in `pm/how-to/decisions.md`.
- Action gehoert in die Info-Sektion fuer Decisions; **nicht** als
  Edit-Button an alten Decisions.

## Done when
- Button "Neue Decision" sichtbar in der Decisions-Info-Sektion.
- Klick → Inline-Form mit Slug + (optional) "Supersedes: ..."-Feld.
- Submit → Datei `pm/decisions/NNNN-<slug>.md` existiert mit Template,
  korrekter naechster Nummer; UI springt in den Edit-Mode der neuen
  Decision.
- An bestehenden Decisions taucht weiterhin **kein** Edit-Button auf.
- Supersedes-Feld leer → keine Supersedes-Zeile in der Datei.
  Supersedes-Feld gefuellt → Zeile `Supersedes: <wert>` im Header.

## Out of scope
- Automatische Verlinkung der superseded Decision (kein Backref-
  Schreiben in die alte Datei).
- Hardcoded Liste alter Decisions.
- Validierung, dass die genannte Supersedes-Datei existiert.

See: pm/how-to/decisions.md

## Plan
- **Server-Endpoint**: `app/pm.php` bekommt
  `POST ?action=new_decision`.
  - Body: JSON `{ "slug": "...", "supersedes": "..." | "" }`.
  - Slug-Regex wie 0003/0004. Supersedes-Feld optional, freitext
    (Format `NNNN-slug` oder leer). Keine Validierung dass die genannte
    Decision existiert (out-of-scope laut Notes).
  - Naechste Nummer: `scandir("pm/decisions")`, max+1, 4-stellig.
  - Zielpfad: `pm/decisions/<NNNN>-<slug>.md`. Bei Kollision → 409.
  - Template fest verdrahtet, basierend auf
    `pm/how-to/decisions.md`:
    ```
    # NNNN — <slug-titlecased>

    [Supersedes: <wert>]              # nur wenn supersedes != ""

    - 
    ```
  - Atomar tmp+rename.
  - Antwort: `{ok:true, path, number:NNNN}`.
  - Append-only-Garantie: dieser Endpoint **erstellt** nur neue
    Dateien — Save-Endpoint (014/0001) blockt das Ueberschreiben
    bestehender Decisions. Damit ist die Regel "nie editieren" hart.
- **Sidebar-UI**: `info.php` bekommt im
  `pm/decisions`-Details-Block einen `.btn-new-decision`-Button und
  ein `.new-decision-form` (Slug-Input + optionales Supersedes-Input).
  An vorhandenen Decision-Links **kein** Edit-Button (sichergestellt
  durch 0002).
- **Sidebar-JS** in `plan_loader.js`: analog 0003/0004.
- **Files touched**: `app/pm.php`, `app/info.php`,
  `app/_share/js/plan_loader.js`.

## Verifikation
- `php -l app/pm.php app/info.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf `info.php`:
  - Decisions-Sektion → Button "+ Decision" sichtbar.
  - Submit Slug `plan-smoke` + Supersedes leer → Datei
    `pm/decisions/<NNNN>-plan-smoke.md` ohne `Supersedes:`-Zeile.
  - Submit Slug `plan-smoke-2` + Supersedes `0007-editor-vendoring` →
    Datei enthaelt `Supersedes: 0007-editor-vendoring` im Header.
  - In beiden Faellen oeffnet sich die neue Decision im Edit-Mode.
  - Cleanup: `rm pm/decisions/<NNNN>-plan-smoke*.md`.
- Bei `ls pm/decisions/` heute (0001-0007) bekommt die naechste
  Decision die Nummer 0008.
- An bestehenden Decisions (z.B. 0007) erscheint **kein** Edit-Button
  (Bestaetigung dass 0002 die Decision-Guard hart durchsetzt).
- `git status` clean, keine `*.tmp.*`.

## Out of scope (Plan)
- Backref-Schreiben in die superseded Decision.
- Validierung dass `supersedes` existiert.
