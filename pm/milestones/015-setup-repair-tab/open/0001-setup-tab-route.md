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
