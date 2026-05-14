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
