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
