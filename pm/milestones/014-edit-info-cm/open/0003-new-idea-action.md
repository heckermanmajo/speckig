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
