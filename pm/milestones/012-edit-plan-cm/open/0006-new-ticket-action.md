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
