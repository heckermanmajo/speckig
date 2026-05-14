# 0002 — plan.php + Sidebar mit Milestones und Bugs

Blocked by: 0001

## Done when

- Neue Datei `app/plan.php`, struktureller Aufbau analog zu `app/index.php`:
  - Bootstrap (`include _share/init.php`).
  - Header via geteiltem Header-Snippet aus 0001 (`Plan`-Link aktiv).
  - `<main>` mit `<nav>`-Sidebar links + `<article id="content">` rechts (gleiches Grid-Layout wie `index.php`).
- Sidebar zeigt:
  - **Milestones** (Ueberschrift `<h2>Milestones</h2>`):
    - Active (Status != `done`/`dropped`) oben, jeweils als `<details>` (default geschlossen, konsistent mit Tree-Default seit dem letzten UI-Update). Summary: `slug — title (status)`. Inhalt: zwei Listen `Open` und `Done`, je `<a>`-Links zu den Ticket-md-Dateien (mit `?path=pm/...`).
    - Archived (Status `done`) unten als eigene Sektion `<h3>Archived</h3>`, gleiche Struktur, aber default zugeklappt.
  - **Bugs** (Ueberschrift `<h2>Bugs</h2>`):
    - Open und Archive je als `<details>`, Inhalt: Links zu Bug-md-Dateien.
  - Jeder Milestone- und Bug-Eintrag ist ein Link auf seine `milestone.md` bzw. die Ticket/Bug-md, sodass der rechte Bereich sie rendern kann (siehe 0003).
- Sidebar-Daten kommen aus `pm_reader::list_milestones()` und `pm_reader::list_bugs()` (aus 0001).
- Layout-CSS wird wo noetig in `app/index.php` ergaenzt, weil das `<style>`-Block dort lebt — oder ausgezogen in `app/_share/html/`. Entscheide pragmatisch. Klassen-Praefix `plan-` zur Vermeidung von Kollisionen mit `nav`-Tree-Styles.
- Keine AJAX-Logik in 0002 — Klicks auf Sidebar-Links fuehren initial einfach zu `?path=...` als URL-Parameter. AJAX-Render des rechten Panels ist 0003.
- `@spec`-Bloecke an neuen Funktionen.

## Verifikation

1. `php -l app/plan.php` sauber.
2. `php -S 127.0.0.1:8099 -t app`, `curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8099/plan.php` -> 200.
3. `curl -s http://127.0.0.1:8099/plan.php | grep -c "milestone"` -> mindestens ein Treffer, Sidebar zeigt Milestones.
4. Browser-Sicht (manuell): Header zeigt `Files`, `Plan` (aktiv), `Plan` als aktive View markiert. Sidebar listet `005-spec-parser` als Active, `001`-`004` als Archived. Klick auf einen Milestone-Eintrag laedt `?path=pm/milestones/005-spec-parser/milestone.md` (rechts noch leer/Platzhalter, weil Render ist 0003).
5. Server stoppen.

## Out of scope

- AJAX-Render des Markdown rechts — 0003.
- Inline-Edit, Status-Aenderungen, Bot-Aktionen.
- Suche/Filter.

## Done

### Files

- `app/plan.php` (neu): zweite Hauptansicht. Bootstrap analog `index.php`,
  Header via `_share\html\header::render("plan", ...)`, Sidebar mit
  Milestones (active + archived) und Bugs (open + archive), rechtes Panel
  als Platzhalter. `?path` wird heute nur durchgereicht (validierter
  String fuer Header-Label, kein AJAX-Render — das ist 0003).
- `app/_share/css/app.css` (neu): geteiltes Stylesheet, ausgezogen aus
  dem `<style>`-Block in `index.php`. Enthaelt zusaetzlich die neuen
  `plan-`-Selektoren fuer die Sidebar.
- `app/index.php` (geaendert): inline `<style>`-Block ersetzt durch
  `<link rel="stylesheet" href="/_share/css/app.css">`. Sonst keine
  semantische Aenderung.

### Style-Auszieh-Entscheidung: dupliziert vs. ausgezogen

Ausgezogen in `app/_share/css/app.css`. Begruendung:

- Inline-Block in `index.php` waere sonst in `plan.php` 1:1 zu duplizieren —
  Header- und Layout-Regeln sind beiden Views gemeinsam (`body`, `header`,
  `main`, `nav`, `article`, `.header-nav*`).
- Plan-View bringt 12 neue `plan-`-Selektoren rein. Eine zweite Quelle
  haette die Pflege-Reibung verdoppelt.
- `app/_share/js/` existiert bereits als geteiltes JS-Verzeichnis;
  `app/_share/css/` ist das natuerliche Pendant.
- Decision 0004 sagt "Kaum CSS bleibt — inline `<style>` in `index.php`,
  kein Build-Step": "kein Build-Step" bleibt erhalten (nur `<link>`,
  keine Bundler), und "kaum CSS" ist eine Mengenaussage, keine
  Inline-Pflicht. Eine geteilte Datei verstoesst nicht gegen den Geist
  der Decision.
- Tree-spezifische Klassen (`.spec-view*`, `.content-tab*`) bleiben in
  `app.css` mit drin: sie haben keine Side-Effects auf `plan.php`, weil
  das HTML dort die Klassen nicht enthaelt. Eine zweite View-spezifische
  CSS-Datei waere Overkill in V1.

### Tree-Collapse-JS: warum eingebunden

`tree_collapse.js` greift auf `details[data-path]`. Plan.php gibt jedem
Milestone- und Bug-`<details>` ein `data-path` (Milestones:
`pm/.../milestone.md`, Bugs: `pm/bugs/open` bzw. `pm/bugs/archive`).
Damit persistiert der Open/Closed-Zustand der Sidebar-Sektionen ueber
Reloads — gleiches UX-Verhalten wie Tree-View. `helpers.js` ist die
Voraussetzung dafuer (`local_get`/`local_set`). `content_loader.js`
bleibt absichtlich raus, weil 0002 keinen Render-Pfad im rechten Panel
hat (kommt in 0003 mit eigenem Loader).

### Verifikation

- `php -l app/plan.php` -> No syntax errors detected.
- `php -l app/index.php` -> No syntax errors detected.
- `php -S 127.0.0.1:8099 -t app` gestartet (Bash background, NICHT 8083).
- `curl -s -o /dev/null -w "%{http_code}" /plan.php` -> 200.
- `curl -s /plan.php | grep -o "plan-milestone-summary" | wc -l` -> 8
  (mind. 005-spec-parser, 006-plan-view + alle archivierten Milestones +
  beide Bug-Sektionen tragen die Klasse). Hinweis: das Server-HTML hat
  keine Newlines, daher `grep -o | wc -l` statt `grep -c` — `grep -c`
  zaehlt Zeilen, nicht Treffer.
- `curl -s /plan.php | grep -o "plan-ticket-link" | wc -l` -> 31
  (Tickets sichtbar).
- `curl -s /plan.php | grep -o "header-nav-link" | wc -l` -> 2
  (zwei Links im HTML; CSS-Selektoren sind nach Style-Auszug nicht mehr
  im HTML zu finden, sondern in `/_share/css/app.css`).
- `curl -s /plan.php | grep -o 'aria-current="page"'` -> einmal, auf dem
  `Plan`-Link (`<a href="/plan.php" class="header-nav-link active"
  aria-current="page">`).
- `curl -s /index.php | grep -o "plan-milestone-summary" | wc -l` -> 0
  (Files-View hat keine Plan-Sidebar).
- `curl -s /plan.php | grep -o 'href="/plan.php?path=pm/[^"]*"' | head -3`:
  - `href="/plan.php?path=pm/milestones/005-spec-parser/milestone.md"`
  - `href="/plan.php?path=pm/milestones/005-spec-parser/open/0003-js-parser.md"`
  - `href="/plan.php?path=pm/milestones/005-spec-parser/open/0004-parser-fixture-tests.md"`
- `curl -s -o /dev/null -w "%{http_code}" /_share/css/app.css` -> 200
  (Stylesheet wird ausgeliefert).
- `curl -s -o /dev/null -w "%{http_code}" /index.php` -> 200 (Tree-View
  weiterhin gruen nach Style-Auszug).
- Server via `TaskStop` beendet, Port 8083 nicht angefasst.
- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"`
  -> nur `./app.sqlite` (kanonisch).

### Edge-Cases / Notizen

- `data-path` der Bug-Sektionen zeigt auf den Ordner (`pm/bugs/open`),
  nicht auf eine `.md`. Das ist fuer `tree_collapse.js` egal — es nutzt
  den Wert nur als localStorage-Key. Click-Targets sind die `<a>`-Links
  innerhalb, nicht das `<details>` selbst.
- `render_tickets_block` gibt leeren String zurueck wenn keine Tickets
  da sind — also kein leeres `<h4>+<ul>`-Paar. Bei Bugs ist die
  Default-Empty-Anzeige `<p class="plan-tickets-empty">keine</p>` statt
  Lueche, damit beide Bug-Sektionen sichtbar bleiben (heute ist
  `pm/bugs/open` leer, dort siehst du den Hinweis).
- Reflected-XSS-Schutz beim Header-Pfad-Label: `?path` muss mit `pm/`
  beginnen, kein `..`, kein fuehrender `/` — sonst landet leerer String
  im Header-Label (gleicher Vertrag wie `pm_reader::read_markdown`).
- Style-Auszug ersetzt in `index.php` nur den `<style>`-Block durch
  `<link>`. Sonst keine Aenderung an `index.php` — keine
  Tree-Render-Logik angefasst, keine `header::render`-Aenderung.
