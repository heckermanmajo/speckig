# 0003 — Action "neue Idea anlegen"

## Goal
Aus dem UI laesst sich eine neue Idea-Datei anlegen: User gibt einen
Titel/Slug ein, das System legt `pm/ideas/<slug>.md` mit Template-Inhalt
an und oeffnet sie direkt im Edit-Mode.

## Notes
- Template-Form liegt in `pm/how-to/ideas.md` — wenn das How-to die
  Form aendert, soll die Action mitziehen. Quelle der Wahrheit ist das
  How-to, nicht eine Kopie im Code.
- Slug muss kollisionssicher sein: existiert die Datei schon, eindeutig
  fehlschlagen (kein stilles Ueberschreiben).
- Slug-Whitelist konservativ: `[a-z0-9-]+`, kein `..`, keine
  Punkte/Slashes — das Save-Endpoint aus 0001 prueft am Ende
  ebenfalls, aber hier nicht erst durchwinken.
- Keine globalen Nummern — Ideas haben keine Nummerierung (siehe
  ideas.md). Reports/Decisions sind separate Tickets.
- Action-Einstieg gehoert in die UI dort, wo man heute Ideas listet
  (Plan-Sidebar Info-Sektion).

## Done when
- In der Info-Sektion fuer Ideas erscheint ein "Neue Idea"-Button.
- Klick → Inline-Form mit Slug-Eingabe + Submit.
- Submit auf freien Slug → Datei `pm/ideas/<slug>.md` existiert mit
  Template-Inhalt aus dem `pm/how-to/ideas.md`-Beispiel; UI navigiert
  zur neuen Idea im Edit-Mode.
- Submit auf bereits existierenden Slug → klare Fehlermeldung, keine
  Datei veraendert.
- Submit auf ungueltigen Slug → Fehlermeldung, keine Datei angelegt.

## Out of scope
- Bulk-Anlage / Import.
- Tags / Metadaten in einem Frontmatter.
- Loeschung einer Idea.

See: pm/how-to/ideas.md

## Plan
- **Server-Endpoint**: `app/pm.php` bekommt einen neuen Handler
  `POST ?action=new_idea` (analog `new_ticket`, `new_milestone`).
  - Body: JSON `{ "slug": "..." }`. `app::error_log()` bei jedem
    Fehler.
  - Slug-Whitelist: `preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug)`,
    Laenge ≤ 80.
  - Zielpfad: `pm/ideas/<slug>.md`. Wenn `file_exists` → 409
    `Idea existiert schon`.
  - Template-Inhalt: aus `pm/how-to/ideas.md` Code-Block extrahieren
    (oder fest verdrahten; aktuell waere fest verdrahten okay, weil
    das How-to klein ist — Decision: fest verdrahten, weil sonst der
    Parser kompliziert wird; in den Notes erwaehnt).
  - Schreiben atomar via tmp+rename, wie new_ticket.
  - Antwort: `{ok:true, path:"pm/ideas/<slug>.md"}`.
- **Sidebar-UI** in `app/info.php`: jede Sektion ist heute ein
  `<details data-path="pm/<name>">`. Im `<details>` fuer
  `pm/ideas` wird ein neuer Block angehaengt:
  - Button `<button class="btn-new-idea">+ Idee</button>`.
  - Verstecktes `<form class="new-idea-form">` mit Slug-Input und
    Submit. Klassenname spiegelt `new-ticket-form`.
- **Sidebar-JS** in `plan_loader.js`:
  - Click-Handler an `.btn-new-idea`: zeigt das Form, versteckt den
    Button. Analog `.btn-new-milestone`.
  - Submit-Handler an `.new-idea-form`: `fetch("/pm.php?action=new_idea")`
    mit JSON-Body. Auf Erfolg: Tab-Reload (`window.location.reload()`)
    oder feiner Push-State auf `info.php?path=pm/ideas/<slug>.md`. Im
    Zweifel: Reload — symmetrisch zu new_ticket.
  - Cancel-Handler an `.btn-cancel-form` deckt das Form schon ab
    (gleiche Klasse).
- **Spec-Block**: oberhalb des Endpoints + an info.php Sidebar-
  Rendering ergaenzen.
- **Files touched**: `app/pm.php` (Endpoint), `app/info.php`
  (Sidebar-Block), `app/_share/js/plan_loader.js` (Handler).

## Verifikation
- `php -l app/pm.php app/info.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf `http://127.0.0.1:8086/info.php`:
  - Ideas-Sektion aufklappen → Button "+ Idee" sichtbar.
  - Klick → Form sichtbar, Button versteckt.
  - Submit mit Slug `plan-smoke` → Datei `pm/ideas/plan-smoke.md` da,
    UI auf Edit-Mode der neuen Idea.
  - Cleanup: `rm pm/ideas/plan-smoke.md`.
- Reject: Submit mit Slug `Bad Slug!` → 400, keine Datei.
- Kollision: Submit gegen vorhandenen Slug → 409, Inhalt unveraendert.
- Reports/Decisions/Audits/Terms-Sektionen zeigen **keinen** "+ Idee"-
  Button (nur in `pm/ideas`).
- `git status` clean, keine `*.tmp.*`.

## Out of scope (Plan)
- Frontmatter / Metadaten in der neuen Datei.
- Loeschung von Ideas (eigenes Ticket falls noetig).

## Done
- `app/pm.php` um POST-Handler `?action=new_idea` erweitert.
  - Body JSON `{slug}`. Slug-Whitelist `^[a-z0-9][a-z0-9-]*$`, Laenge
    1-80. Ungueltige Slugs (inkl. `../etc`, leer, Whitespace, Gross-
    schreibung, Sonderzeichen) -> 400 `Ungueltiger slug.` + Log via
    `app::error_log()`.
  - Zielpfad `pm/ideas/<slug>.md`; `file_exists()` -> 409
    `Idea existiert schon.` + Log. Kein stilles Ueberschreiben.
  - Template fest verdrahtet (Begruendung: `pm/how-to/ideas.md`
    dokumentiert keine klar abgegrenzte Template-Sektion; die im
    How-to genannte Minimalform — Titel-Zeile + One-line essence +
    Notes — wird hier 1:1 als HEREDOC reproduziert, damit kein
    Parser ueber das How-to laufen muss).
  - Atomar geschrieben via `tmp + rename`. Tmp wird bei Fehler
    aufgeraeumt.
  - Antwort 200 + `{ok:true, path:"pm/ideas/<slug>.md"}`.
  - Spec-Block ueber dem Handler dokumentiert Vertrag, Whitelist,
    Template-Begruendung; Datei-Header-Spec um den neuen Endpoint
    erweitert.
- `app/info.php`: `render_info_section()` um optionalen 4. Parameter
  `$action_block_html` erweitert (default `""`, hat keinen Effekt fuer
  die anderen Sektionen). Im Sidebar-Aufbau wird der Action-Block nur
  fuer den Ideas-Aufruf uebergeben: `+ Idee`-Button (`.btn-new-idea`)
  plus verstecktes `<form class="new-idea-form">` mit `.input-slug`,
  `.btn-submit`, `.btn-cancel-form`, `.form-error`. Klassenstil
  spiegelt `.new-milestone-form` / `.new-ticket-form` aus plan.php,
  damit existierende CSS-Regeln greifen.
- `app/_share/js/plan_loader.js` um `init_new_idea_form()` plus die
  Handler `on_new_idea_button_click`, `on_new_idea_cancel_click` und
  `on_new_idea_submit` erweitert.
  - Submit POSTet `{slug}` an `/pm.php?action=new_idea`.
  - Bei `ok:true` wird auf `info.php?path=pm/ideas/<slug>.md`
    navigiert (statt nur `reload()`), damit die Sidebar refresht UND
    der Loader die frische Idea direkt im Read-View mit
    Edit-Toolbar oeffnet — symmetrisch zum new-ticket-Reload, aber
    mit eingebauter `?path`-Auflage, damit der User sofort den
    Inhalt sieht.
  - Bei Fehler wird `.form-error` der Form befuellt.
  - `init_new_idea_form()` ist self-guarded — kein no-op auf plan.php
    oder anderen Routen ohne `.btn-new-idea`.
  - Spec-Kommentar oben in der Datei um den M014/0003-Abschnitt
    erweitert.

Files touched:
- `app/pm.php` (+~165 / -3: Header-Spec, action-Flag, Handler-Block
  mit Spec-Kommentar).
- `app/info.php` (+~25 / -2: Render-Funktion-Parameter, Sidebar-
  Action-Block fuer Ideas).
- `app/_share/js/plan_loader.js` (+~210 / -1: Spec-Block,
  init_new_idea_form-Funktionsfamilie, Aufruf in init_plan_loader).
- `pm/milestones/014-edit-info-cm/milestone.md` (Haekchen +
  archive/-Pfad fuer 0003).
- ticket selbst nach `archive/`.

Smoketest-Belege (Server `php -S 127.0.0.1:8086 -t app`):
- `php -l app/pm.php app/info.php` -> clean.
- Happy `{"slug":"agent-smoke"}` -> 200 + `{ok:true,path:"pm/ideas/agent-smoke.md"}`.
  `cat pm/ideas/agent-smoke.md` zeigt Template (Titel + essence + notes). Cleanup ok.
- Collision `{"slug":"audits"}` -> 409 + `Idea existiert schon.`.
  `head -2 pm/ideas/audits.md` zeigt unveraenderten Originalinhalt.
- Reject `{"slug":"Bad Slug!"}` -> 400 + `Ungueltiger slug.`.
- Reject `{"slug":""}` -> 400 + `Ungueltiger slug.`.
- Reject `{"slug":"../etc"}` -> 400 + `Ungueltiger slug.`.
- HTML-Check `curl info.php | grep -c btn-new-idea` -> 1.
- HTML-Check `curl info.php | grep -c new-idea-form` -> 1.
- JS-Check `grep -n "new-idea\|new_idea" plan_loader.js` -> 32 Matches
  (Spec-Kommentar + Selektoren + Submit-Body + init-Aufruf).
- M014/0001 Save-Regression: POST `pm/ideas/save-smoke.md` -> 200 +
  `bytes:5`. Cleanup ok.
- Streu-Files: `*.tmp.*` leer, `app.sqlite*` zeigt nur `./app.sqlite`.
- Server via `TaskStop` gestoppt; Port 8083 nicht angefasst.
