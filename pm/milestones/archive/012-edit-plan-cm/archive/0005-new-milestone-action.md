# 0005 — Action "neuer Milestone" in der Plan-Sidebar

Oben in der Plan-Sidebar ein Button "+ Milestone", der ein Inline-
Formular (Slug + Titel) zeigt. Submit ruft `POST /pm.php?action=
new_milestone` und legt
`pm/milestones/NNN-<slug>/{milestone.md, open/.gitkeep, archive/.gitkeep}`
an. Sidebar wird neu geladen.

## Done when
- `app/pm.php` neuer Pfad `?action=new_milestone`:
  - Body JSON: `{slug, title}`.
  - `slug`: lowercase ASCII + Bindestriche, 1-60 Zeichen, sonst 400.
  - `title`: 1-120 Zeichen.
  - Naechste freie NNN: max(NNN unter `pm/milestones/` UND
    `pm/milestones/archive/`) + 1, dreistellig.
  - Legt Folder + drei Files an (analog zu den existierenden
    Skeletten): `milestone.md` mit Template
    ```
    # NNN — <title>

    Goal: <title>.

    Status: planned

    ## Tickets

    ## Out of scope
    ```
  - Antwort `{ok:true, slug:"NNN-slug", path:"pm/milestones/NNN-slug"}`.
- `app/plan.php` zeigt oben (vor dem ersten `<h2>Milestones</h2>`) den
  Button `<button class="btn-new-milestone">+ Milestone</button>` und
  ein zunaechst verstecktes `<form class="new-milestone-form">` mit
  Slug- und Titel-Input plus Submit / Cancel.
- `app/_share/js/plan_loader.js` (oder neue `plan_actions.js`):
  - Click-Handler fuer `.btn-new-milestone`: zeigt Formular.
  - Submit: `fetch` POST, bei Erfolg `window.location.reload()`
    (einfach und ausreichend).

## Verifikation
- `php -l app/pm.php app/plan.php` ok.
- Server 8086.
- `curl -s -X POST -d '{"slug":"test-milestone","title":"Test"}' 'http://127.0.0.1:8086/pm.php?action=new_milestone'` → `{"ok":true,...}`.
- `ls pm/milestones/NNN-test-milestone/` zeigt `milestone.md open archive`.
- `head -5 pm/milestones/NNN-test-milestone/milestone.md` matcht
  Template.
- Cleanup: `rm -rf pm/milestones/NNN-test-milestone` nach Test.
- Fehlerfaelle: `slug` mit Slash, mit Leerzeichen, leer → je 400.
- `curl -s -X POST -d '{"slug":"valid","title":""}' ...` → 400.

## Out of scope
- Form-Validation im Browser jenseits `required`-Attribut — Server ist
  Wahrheits-Quelle.
- Direkt zum neuen Milestone navigieren — Reload reicht.
- Rename / Delete.
- Templates pro Milestone-Typ.

## Done
- `app/pm.php`: neuer POST-Pfad `?action=new_milestone` vor dem
  bestehenden Save-Dispatch. Body wird via `file_get_contents("php://input")`
  gelesen und mit `json_decode` geparst; bei Parse-Fehler 400 +
  `"Body ist kein JSON."`.
- Validierung in Schichten mit benannten Bools:
  `nm_slug_is_string`, `nm_slug_length_ok` (1-60), `nm_slug_charset_ok`
  (`^[a-z0-9-]+$`), `nm_slug_no_edge_dash` — fuehrender oder
  abschliessender Bindestrich wird abgewiesen. Titel wird getrimmt und
  auf 1-120 Zeichen geprueft.
- Naechste freie NNN: `scandir(pm/milestones)` + `scandir(pm/milestones/archive)`,
  Eintraege mit `ctype_digit` auf die ersten drei Stellen geprueft,
  `max() + 1` (Fallback 1 bei leerem Repo), via `sprintf("%03d", $next)`
  dreistellig. Kollisionspruefung mit `file_exists` → 409.
- Folder + Substruktur via `mkdir(0755, recursive=true)` + zwei weitere
  `mkdir`-Calls fuer `open/` und `archive/`; zwei leere `.gitkeep`-Files
  via `file_put_contents`. `milestone.md` wird mit dem geforderten
  Template via HEREDOC geschrieben. Bei jedem FS-Fehler 500 + Log via
  `app::error_log()`. Erfolg loggt `pm.php new_milestone: <NNN-slug>`.
- Antwort `{ok:true, slug:"NNN-<slug>", path:"pm/milestones/NNN-<slug>"}`.
- Spec-Block am Dateianfang um die neue Aktion erweitert.
- `app/plan.php`: direkt vor `<h2>Milestones</h2>` ein neuer
  `<div class="plan-action-block">` mit Button und initial `hidden`em
  `<form class="new-milestone-form">` (slug-Input, title-Input, Submit,
  Abbrechen, `.form-error`).
- `app/_share/js/plan_loader.js`: Spec-Block oben um die new-milestone-
  Action ergaenzt. Neue Funktionen `on_new_milestone_button_click`,
  `on_new_milestone_cancel_click`, `show_new_milestone_error`,
  `on_new_milestone_submit`, `init_new_milestone_form`. Submit
  POSTet JSON an `/pm.php?action=new_milestone`, try/catch um
  `fetch` und `response.json()`, bei `ok:true` `window.location.reload()`,
  bei Fehler `.form-error` mit Server-Message oder Fallback-Text.
  `init_new_milestone_form` ist self-guarded (no-op auf info.php, wo
  Button/Form fehlen) und wird am Ende von `init_plan_loader()`
  aufgerufen.
- `app/_share/css/app.css`: fuenf Mini-Regeln am Ende fuer
  `.plan-action-block`, `.btn-new-milestone`, `.new-milestone-form`
  und `.new-milestone-form .form-error`.
- Stil: BSD-Klammern, `snake_case`, `what_cond_means`-Pattern,
  `app::error_log()` statt direktem `error_log()`. JSON-Antwort braucht
  kein `app::escape()`.

Files touched:
- `app/pm.php` (+228 / -3).
- `app/plan.php` (+13 / -0).
- `app/_share/js/plan_loader.js` (+190 / -1).
- `app/_share/css/app.css` (+24 / -0).
- `pm/milestones/012-edit-plan-cm/milestone.md` (Haekchen + Pfad).
- ticket selbst nach `archive/`.

Smoketest-Belege (Server 8086):
- Happy path `{"slug":"verify-smoke","title":"Verify Smoke"}` →
  `{"ok":true,"slug":"017-verify-smoke","path":"pm/milestones/017-verify-smoke"}`.
  Folder enthielt `archive/  milestone.md  open`, beide `.gitkeep`-Files
  vorhanden, `milestone.md` matchte das Template (`# 017 — Verify Smoke`,
  `Goal:`, `Status: planned`, `## Tickets`, `## Out of scope`).
- NNN-Belegung: hoechste existierende war 016, generiert wurde 017
  (max + 1, dreistellig).
- Guards: alle sechs spezifizierten Faelle (slug mit `/`, leer, Grossbuchstaben,
  Underscore, leerer Titel, Body kein JSON) liefern `{"ok":false,"message":...}`.
  Zusatzcheck: `slug:"-foo"` und `slug:"foo-"` werden ebenfalls abgelehnt.
- Markup: `curl plan.php | grep -c btn-new-milestone` = 1,
  `grep -c new-milestone-form` = 1, `info.php` hat 0 Treffer (Aktion
  nur in plan.php).
- Bestehende Routes `/plan.php`, `/info.php`, `/index.php` weiter 200.
- Save-Endpoint aus 0002 weiter funktional:
  `POST ...action=save&path=pm/ideas/save-smoke.md` → 200 + `bytes:1`.
- Streu-File-Check `find pm/milestones -name "*.tmp.*"` leer; nur
  kanonisches `./app.sqlite`.
- Test-Milestone und Save-Smoke nach den Tests geloescht, `git status`
  zeigt nur die intendierten 4 modifizierten Files.
