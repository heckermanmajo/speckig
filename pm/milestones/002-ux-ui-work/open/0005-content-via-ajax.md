# 0005 — Content via AJAX + History

Klicks auf Tree-Links laden den Content per fetch in die rechte Spalte. URL spiegelt die aktuelle Datei via `history.pushState`. Browser-Back/Forward funktionieren.

See: pm/decisions/0004-ux-policy.md
Blocked by: 0003, 0004

## Done when
- Neue Datei `app/_share/js/content_loader.js` macht:
  - Click-Handler auf Tree-Links: `event.preventDefault()`, fetch `/file.php?path=…`, response.html in `<article>` injizieren.
  - Header rechts/oben zeigt den aktuellen Pfad nach dem Load.
  - `history.pushState({path}, "", "/?path=...")` bei jedem erfolgreichen Load.
  - `popstate`-Handler: lädt entsprechend nach Back/Forward.
- `app/index.php` lädt `content_loader.js` nach `helpers.js`.
- Bei initial-Page-Load mit `?path=…` in der URL: JS macht einen Fetch und füllt den Content.
- Ohne `?path` und ohne Klick: rechts steht `Datei links auswählen.` (wie bisher).
- Smoketest: im Browser auf drei verschiedene Files klicken → URL ändert sich, Content erscheint sofort, kein Full-Reload (Network-Tab zeigt nur file.php-XHRs). Back-Button geht durch die Historie.
