# 0002 — Eigener Info-Tab oben im Header (Files / Plan / Info)

Verschiebt die in 0001 eingefuehrten Info-Sektionen (Ideas, Reports,
Decisions, Audits, Terms) aus der Plan-Sidebar in einen eigenen
dritten Header-Tab `Info` mit eigener Route `app/info.php`. Layout
analog zu `plan.php`: Sidebar links mit fuenf `<details>`-Bloecken,
Render rechts via existierendem `pm.php`-Endpoint. Plan-Sidebar zeigt
nach diesem Ticket wieder nur Milestones + Bugs.

## Done when
- Header (`app/_share/html/header.php`) hat drei Tabs in der
  Reihenfolge: Files, Plan, Info. Aktiver Tab korrekt markiert
  (`active`-Klasse + `aria-current="page"`).
- `header::render()` akzeptiert `"info"` als `$active_view`.
- Neue Route `app/info.php`:
  - Re-Use von `_share\pm_reader::list_info_sections()` (in 0001
    bereits angelegt).
  - Sidebar zeigt fuenf `<details data-path="pm/<name>">`-Bloecke
    (Ideas / Reports / Decisions / Audits / Terms). Empty-State
    `<p class="plan-tickets-empty">keine</p>` bleibt.
  - Klick-Targets `class="plan-ticket-link" href="/info.php?path=..."`,
    damit der bestehende `plan_loader.js` greift.
  - Initial-Render rechts: `<p>Eintrag links auswaehlen.</p>`.
  - Header-Pfad-Label und Repo-Label analog zu `plan.php`.
- Info-Block aus `app/plan.php` entfernt (neue Funktion
  `render_info_section` und die `<h2>Info</h2>`-Sektion fliegen raus).
- `plan_loader.js` so anpassen, dass der `history.pushState`-Pfad nicht
  hartcodiert `/plan.php?path=...` ist, wenn der aktuelle Tab die
  Info-Route ist — d.h. der Loader nimmt `window.location.pathname` als
  Basis statt eines festen Strings. Begruendung: derselbe Loader laeuft
  in plan.php UND info.php (Wiederverwendung gewollt — beide Seiten
  binden `plan_loader.js` ein).
- `pm.php` bleibt unveraendert — der Endpoint funktioniert bereits fuer
  alle `pm/`-Pfade.

## Verifikation
- `php -l app/info.php app/plan.php app/_share/html/header.php` — sauber.
- `php -S 127.0.0.1:8086 -t app` starten, dann:
  - `curl -s http://127.0.0.1:8086/info.php | grep -c 'data-path="pm/ideas"'` → 1
  - `curl -s http://127.0.0.1:8086/info.php | grep -c 'data-path="pm/decisions"'` → 1
  - `curl -s http://127.0.0.1:8086/info.php | grep -c 'data-path="pm/terms"'` → 1
  - `curl -s http://127.0.0.1:8086/info.php | grep -c 'href="/info.php?path='` ≥ 1
  - `curl -s http://127.0.0.1:8086/info.php | grep -c 'aria-current="page"'` → 1
  - `curl -s http://127.0.0.1:8086/info.php | grep -c '>Info<'` ≥ 1
  - `curl -s http://127.0.0.1:8086/plan.php | grep -c '>Info<'` → 0
  - `curl -s http://127.0.0.1:8086/plan.php | grep -c 'data-path="pm/ideas"'` → 0
  - `curl -s http://127.0.0.1:8086/plan.php | grep -c 'data-path="pm/milestones'` ≥ 1
  - `curl -s http://127.0.0.1:8086/index.php | grep -c '>Info<'` ≥ 1 (Header-Tab da)
- Header-Aktivmarkierung: Quelltext jeder Seite enthaelt genau **einen**
  `aria-current="page"`.
- Streu-File-Check: `find . -name "app.sqlite*" -not -path "./.git/*"`
  nur kanonischer Pfad.
- Server-Cleanup: 8086 stoppen, 8083 nicht anfassen.

## Out of scope
- Inline-Edit / Create / Delete im Info-Tab (Milestone M014).
- Neue Inhalte unter `pm/info/` — der Info-Tab buendelt nur die fuenf
  bestehenden Verzeichnisse.
- Reihenfolge / Sortierung anders als pm_reader's Default.
- Suche / Filter.
- CSS-Polish jenseits dessen, was die wiederverwendeten Klassen bringen.

## Done

### Files

- `app/_share/html/header.php` (geaendert): `header::render()` akzeptiert
  jetzt `"files"`, `"plan"` ODER `"info"` als `$active_view`. Neuer
  dritter Nav-Link `<a href="/info.php">Info</a>` nach Files/Plan.
  Aktiv-Markierung (Klasse `active` + `aria-current="page"`) laeuft
  ueber dieselbe `build_nav_link_attrs()`-Helper, kein neuer Code-Pfad.
  Spec-Block erweitert (drei Werte statt zwei).
- `app/info.php` (neu): dritte Hauptansicht. Struktur 1:1 wie plan.php
  (Sidebar links, Article rechts, Header oben), aber:
  - `header::render("info", ...)`,
  - Sidebar enthaelt **nur** die fuenf Info-Sektionen via
    `pm_reader::list_info_sections()` — kein Milestones-Block, kein
    Bugs-Block,
  - lokale Funktion `render_info_section()` strukturell wie das alte
    Pendant in plan.php, aber `href="/info.php?path=..."`,
  - `<title>speckig — info</title>`,
  - Initial-Render: `<p>Eintrag links auswaehlen.</p>`,
  - bindet `helpers.js`, `tree_collapse.js`, `plan_loader.js` ein,
  - `?path=...`-Validation (kein `..`, kein fuehrender `/`, MUSS mit
    `pm/` beginnen) analog zu plan.php — Header-Label-only, kein
    Server-Side-Render des Markdowns,
  - `@spec`-Block am Dateianfang erklaert read-only, pm.php-AJAX,
    Hinweis dass die Plan-Sidebar diese Sektionen nicht mehr enthaelt.
- `app/plan.php` (geaendert):
  - Funktion `render_info_section()` entfernt (zieht nach info.php),
  - `<h2>Info</h2>`-Block und die fuenf `render_info_section(...)`-
    Aufrufe entfernt,
  - `pm_reader::list_info_sections()`-Call entfernt,
  - Variablen-Liste reduziert auf `$milestones` + `$bugs`,
  - `render_milestone_block`, `render_tickets_block`, `render_bugs_block`
    und `header::render("plan", ...)` bleiben unveraendert.
- `app/_share/js/plan_loader.js` (geaendert): Push-State-URL in
  `load_plan_path()` von hartcodiert `"/plan.php?path=..."` auf
  `window.location.pathname + "?path=..."` umgestellt. Damit greift
  derselbe Loader sauber auf /plan.php UND /info.php. Spec-Block oben
  ergaenzt um Dual-Route-Hinweis.

### Verifikation

- `php -l app/info.php app/plan.php app/_share/html/header.php` -> alle
  "No syntax errors detected".
- `php -S 127.0.0.1:8086 -t app` gestartet (Hintergrund, **nicht** 8083).
- `curl -s /info.php | grep -c 'data-path="pm/ideas"'`     -> 1.
- `curl -s /info.php | grep -c 'data-path="pm/decisions"'` -> 1.
- `curl -s /info.php | grep -c 'data-path="pm/terms"'`     -> 1.
- `curl -s /info.php | grep -c 'href="/info.php?path='`    -> 1 Zeile
  (18 Treffer auf der Zeile via `grep -o`, Vertrag `>= 1` erfuellt).
- `curl -s /info.php | grep -c 'aria-current="page"'`      -> 1.
- `curl -s /plan.php | grep -c '>Info<'`                   -> 1
  (Header-Tab — siehe Notizen).
- `curl -s /plan.php | grep -c 'data-path="pm/ideas"'`     -> 0.
- `curl -s /plan.php | grep -c 'data-path="pm/milestones'` -> 1.
- `curl -s /plan.php | grep -c 'aria-current="page"'`      -> 1.
- `curl -s /index.php | grep -c '>Info<'`                  -> 1.
- `find . -name "app.sqlite*" -not -path "./.git/*"` -> nur
  `./app.sqlite` (kanonisch).
- Server via TaskStop beendet; 8083 nicht angefasst.

### Notizen

- Abweichung von der Verifikationsliste: `plan.php | grep -c '>Info<'`
  liefert **1**, nicht **0**, weil der neue dritte Header-Tab
  `<a href="/info.php">Info</a>` auf jeder Seite (Files, Plan, Info)
  gerendert wird — sonst waere die Tab-Leiste nicht konsistent. Der
  Sidebar-Info-Block in plan.php ist sauber entfernt (siehe
  `pm/ideas`-Count = 0). Semantisch erfuellt: in der Plan-Sidebar
  selbst gibt es keine Info-Sektion mehr; das einzige `>Info<` auf
  /plan.php steht im Header-Tab.
- `pm.php` blieb unangefasst — `list_info_sections()` lieferte schon in
  0001 die richtigen Pfade, `pm.php` akzeptiert jeden `pm/<...>.md`.
- Code-Duplikation zwischen `render_info_section` (info.php) und
  `render_bugs_block` (plan.php) ist gewollt — keine vorzeitige
  Abstraktion, der Klick-Selektor `plan-ticket-link` ist bewusst
  geteilt damit `plan_loader.js` ohne Spezialfall greift.
