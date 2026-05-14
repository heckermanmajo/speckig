# 0006 — Action "neues Ticket im Milestone"

Pro Milestone-Block in der Plan-Sidebar ein Button "+ Ticket", der ein
Inline-Formular (Slug + Titel) zeigt. Submit legt
`open/NNNN-<slug>.md` im Milestone an und fuegt einen Bullet-Eintrag in
dessen `milestone.md` vor "## Out of scope" ein.

## Done when
- `app/pm.php` neuer Pfad `?action=new_ticket`:
  - Body JSON: `{milestone_slug, slug, title}`.
  - Validation: `milestone_slug` existiert unter `pm/milestones/`
    (nicht archive), `slug` lowercase-ASCII+Bindestriche 1-60,
    `title` 1-120.
  - Naechste NNNN: max NNNN in `open/` + `archive/` des Milestone + 1,
    vierstellig.
  - Erzeugt `open/NNNN-<slug>.md` mit Template
    ```
    # NNNN — <title>

    <title>.

    ## Done when
    -

    ## Verifikation
    -

    ## Out of scope
    -
    ```
  - Modifiziert `milestone.md`: liest Zeilen, sucht "## Out of scope",
    fuegt VOR dieser Zeile einen Bullet `- [ ] open/NNNN-<slug>.md`
    in die Tickets-Liste ein. Wenn "## Tickets" fehlt, wird der ganze
    Block davor eingefuegt.
  - Antwort `{ok:true, path:"pm/milestones/<ms>/open/NNNN-<slug>.md"}`.
- Plan-Sidebar:
  - Pro Milestone-Block `<button class="btn-new-ticket" data-
    milestone-slug="...">+ Ticket</button>` und verstecktes Inline-
    Formular.
- JS (in `plan_actions.js` bzw. `plan_loader.js`):
  - Click-Handler attached pro Button, Submit `fetch`, Reload.

## Verifikation
- `php -l app/pm.php app/plan.php` ok.
- Server 8086.
- Setup-Test: zuerst ein Test-Milestone via 0005 anlegen
  (`test-milestone`).
- `curl -s -X POST -d '{"milestone_slug":"NNN-test-milestone","slug":"foo","title":"Foo bar"}' 'http://127.0.0.1:8086/pm.php?action=new_ticket'` → `{"ok":true,...}`.
- `ls pm/milestones/NNN-test-milestone/open/` zeigt `0001-foo.md`.
- `head -3 pm/milestones/NNN-test-milestone/open/0001-foo.md` matcht
  Template.
- `grep -c '0001-foo.md' pm/milestones/NNN-test-milestone/milestone.md`
  → 1.
- Cleanup: gesamtes Test-Milestone-Verzeichnis loeschen.
- Fehler: ungueltiger `milestone_slug` → 400.

## Out of scope
- Ticket-Reihenfolge anders als by-NNNN.
- Templates pro Ticket-Typ.
- Sofortiger Wechsel in den Edit-Modus nach Anlegen.
- Bulk-Anlage mehrerer Tickets.

## Done
- `app/pm.php`: neuer POST-Pfad `?action=new_ticket` vor dem
  bestehenden new_milestone-Dispatch. Body via
  `file_get_contents("php://input")` + `json_decode`; bei Parse-Fehler
  400 + `Body ist kein JSON.`.
- Validierung in Schichten, alles mit benannten Bools:
  - `milestone_slug`: String, 1-100 Zeichen, charset `^[a-z0-9-]+$`,
    Form `^[0-9]{3}-` (drei Ziffern + Bindestrich). Sonst 400
    `Ungueltiger milestone_slug.`.
  - Ordner-Existenz: `is_dir(pm/milestones/<slug>)`. Archive-Pfade
    (`pm/milestones/archive/...`) werden bewusst NICHT akzeptiert —
    der Subpfad muss aktiv sein. Sonst 400 `Milestone existiert nicht.`.
  - `slug`: 1-60 Zeichen, `^[a-z0-9-]+$`, kein fuehrender/abschliessender
    Bindestrich. Sonst 400 `Ungueltiger slug.`.
  - `title`: getrimmt 1-120. Sonst 400 `Ungueltiger Titel.`.
- Naechste NNNN: `scandir(open/)` + `scandir(archive/)`, jeweils `.md`-
  Eintraege mit `ctype_digit` auf die ersten vier Zeichen, `max()+1`
  (Fallback 1). `sprintf("%04d", ...)` vierstellig.
- Kollisionspruefung: `file_exists()` auf open/ UND archive/. Sonst 409.
- Ticket-File via `file_put_contents` (Ordner existiert, File nicht).
  Template via HEREDOC: Header `# NNNN — <title>`, dann `<title>.`,
  dann `## Done when -`, `## Verifikation -`, `## Out of scope -`.
- Bullet in `milestone.md` einfuegen: `file()` liefert Zeilen,
  Position von `## Out of scope` per `str_starts_with(ltrim($line),
  "## Out of scope")`, dann rueckwaerts bis zur letzten nicht-leeren
  Zeile — Bullet DANACH (vor der Leerzeile, die `## Out of scope`
  voraus geht). Edge case (keine Out-of-Scope-Section): Append am
  Ende mit `## Tickets`-Section falls noetig. Atomar via
  `tmp+rename`, mit tmp-Cleanup bei Fehler.
- Antwort `{ok:true, path:"pm/milestones/<ms>/open/NNNN-<slug>.md",
  slug:"NNNN-<slug>"}`. Jede Abweisung loggt via `app::error_log()`.
- Spec-Block am Dateianfang um die neue Aktion erweitert.
- `app/plan.php`: `render_milestone_block()` erweitert. Pro AKTIVEM
  Milestone (`! str_starts_with($path, "pm/milestones/archive/")`)
  ein `<div class="plan-action-block">` am Ende des `body` mit Button
  `.btn-new-ticket` und initial `hidden`em `<form class="new-ticket-form">`.
  Beide tragen `data-milestone-slug="<slug>"` zum Pairing.
- `app/_share/js/plan_loader.js`: Spec-Block um die new-ticket-Action
  erweitert. Neue Funktionen `show_new_ticket_error`,
  `on_new_ticket_button_click`, `on_new_ticket_cancel_click`,
  `on_new_ticket_submit`, `init_new_ticket_forms`. Selektoren laufen
  RELATIV zur eigenen Form (`form.querySelector(".btn-cancel-form")`
  bzw. `form.querySelector(".form-error")`) — kein Bleed in die
  new-milestone-Form oder andere Ticket-Forms. Pairing per
  `data-milestone-slug`-Attribut + `closest(".new-ticket-form")`.
  `init_new_ticket_forms` wird am Ende von `init_plan_loader()`
  aufgerufen und ist self-guarded (no-op ohne Buttons/Forms).
- `app/_share/css/app.css`: zwei Mini-Regeln am Ende fuer
  `.btn-new-ticket` (weniger prominent, kleinere Schrift) und
  `.new-ticket-form` (flex column gap). `.form-error`-Regel aus 0005
  passt via Klassen-Selektor ohne Aenderung.
- Stil: BSD-Klammern, `snake_case`, `$what_cond_means`-Pattern,
  `app::error_log()` statt direktem `error_log()`.

Files touched:
- `app/pm.php` (+325 / -1).
- `app/plan.php` (+22 / -0).
- `app/_share/js/plan_loader.js` (+249 / -1).
- `app/_share/css/app.css` (+16 / -0).
- `pm/milestones/012-edit-plan-cm/milestone.md` (Haekchen + Pfad).
- Ticket selbst nach `archive/`.

Smoketest-Belege (Server 8086):
- Setup: new_milestone `{slug:"t06",title:"T06"}` → `017-t06` mit
  Standard-Template (Status: planned, leere `## Tickets`, leere
  `## Out of scope`).
- Happy path `{milestone_slug:"017-t06",slug:"foo",title:"Foo Bar"}` →
  `{"ok":true,"path":"pm/milestones/017-t06/open/0001-foo.md",
  "slug":"0001-foo"}`. Datei vorhanden, head matcht `# 0001 — Foo Bar`,
  Bullet `- [ ] open/0001-foo.md — Foo Bar` zwischen `## Tickets`
  und `## Out of scope` (mit Leerzeile davor und danach).
- NNNN-Counter: zweites Ticket `{slug:"bar"}` → `0002-bar`, Bullet
  korrekt unten an die Tickets-Liste angehaengt.
- Guards (alle `{"ok":false,...}`): milestone_slug `999-nope`,
  archivierter `011-ideas-and-info-tab`, slug `foo/bar`, slug `""`,
  title `""`, body kein JSON. Im open/ entstand kein Streu-Ticket.
- Markup: `grep -o btn-new-ticket plan.html | wc -l` = 6 bei 6 aktiven
  Milestones (012-017, inkl. dem Test-017-t06). `new-ticket-form`
  ebenfalls 6. Archivierte 11 Milestones tragen keinen Button.
- Regression: plan.php, index.php, info.php weiter 200; save-endpoint
  weiter `{"ok":true,...}`; new_milestone-Endpoint weiter aktiv mit
  Slug-Validierung.
- Cleanup: `rm -rf pm/milestones/017-t06`; `git status --short` zeigt
  nur die intendierten 4 modifizierten Files; keine `*.tmp.*`-Files
  uebrig; nur kanonisches `./app.sqlite`.
