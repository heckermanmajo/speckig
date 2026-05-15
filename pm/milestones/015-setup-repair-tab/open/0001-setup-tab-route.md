# 0001 — Setup/Repair-Tab im Header + Route

## Goal
Im Header gibt es einen vierten Tab "Setup / Repair", der auf eine eigene
Setup-Seite navigiert. Die Active-View-Erkennung markiert den Tab korrekt
und die Seite rendert leer (Inhalt kommt aus den naechsten Tickets).

## Notes
- Header-Markup lebt in `app/_share/html/header.php`; bestehende Tabs
  nicht umarrangieren — neuer Tab haengt rechts an die existierenden
  an.
- Active-View-Logik in den bestehenden Routen muss `setup` als
  legitimen Wert kennen — kein hartes Default-Fallback auf "files".
- Route ist ein eigenes Entry-File `app/setup.php`, damit das
  Routing-Muster konsistent zu `plan.php` / `info.php` bleibt.
- Auf dem Setup-Tab soll man auch ohne SPECKIG_ROOT etwas Sinnvolles
  sehen koennen — die Seite ist genau fuer den Fall da, dass die Welt
  kaputt ist.

## Done when
- Header zeigt 4 Tabs in dieser Reihenfolge: Files, Plan, Info,
  Setup / Repair.
- Klick auf "Setup / Repair" laedt eine Seite unter
  `http://.../setup.php` (oder `?view=setup`, je nach Routing-Muster)
  mit ausgewaehltem Header-Tab.
- Seite rendert ohne PHP-Fehler, auch wenn noch keine Checks
  implementiert sind.
- Die anderen drei Tabs bleiben optisch und funktional unveraendert.

## Out of scope
- Checks (0002/0003).
- Repair-Endpoints (0004).
- Baseline-Bundle (0005).
- Inhalts-Design der Seite — Skelett reicht.

See: pm/how-to/code_style.md

## Plan
- **Entry-File**: neues `app/setup.php` anlegen, Struktur 1:1 wie
  `app/info.php` — `<?php`-Header, Spec-Block, `use _share\app;
  _share\html\header;`, `init.php`-Include, Repo-Root-Resolution,
  `header::render("setup", ...)` aufrufen, dann ein minimaler
  `<article id="content">`-Container mit Placeholder-Text. Sidebar
  bleibt fuer 0001 leer (kommt in 0002).
- **Header erweitern**: `app/_share/html/header.php`:
  - In `render()` neuen Block:
    `$setup_is_active = $active_view === "setup";`
    `$setup_link_attrs = header::build_nav_link_attrs($setup_is_active);`
  - Im HTML-String anhaengen: `<a href="/setup.php" ...>Setup / Repair</a>`
    nach dem Info-Link.
  - Spec-Block oben anpassen: `$active_view` akzeptiert jetzt auch
    `"setup"`.
- **Active-View-Strenge**: bestehende Routen (`index.php`, `plan.php`,
  `info.php`) bleiben unveraendert. Sie geben weiterhin ihren eigenen
  active-View-String mit, der Header faellt nie auf einen Default
  zurueck.
- **CSS**: `app/_share/css/app.css` — neuer Tab passt automatisch in
  die `.header-nav`-Liste; keine Aenderung noetig.
- **Files touched**: `app/setup.php` (neu), `app/_share/html/header.php`.

## Verifikation
- `php -l app/setup.php app/_share/html/header.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- `curl -s http://127.0.0.1:8086/setup.php` → 200, HTML enthaelt
  Header mit allen 4 Tabs, `Setup / Repair` hat `aria-current="page"`.
- `curl -s http://127.0.0.1:8086/index.php | grep -c 'href="/setup.php"'`
  → 1 (Header zeigt 4 Tabs).
- Browser: Klick auf "Setup / Repair" navigiert auf setup.php, Tab
  ist optisch aktiv.
- Klick auf andere Tabs funktioniert weiter, Setup-Tab ist dort nicht
  aktiv.
- `git status` clean.

## Out of scope (Plan)
- Inhalt der Setup-Seite (0002/0003).
- JS-Loader fuer Setup (kommt erst, wenn dynamische Re-Runs noetig
  werden).
