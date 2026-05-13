# 0004 — Speckig-Index-Seite

See: pm/decisions/0002-php-infra.md
Blocked by: 0001, 0002

`app/index.php` zeigt aktuell die Login-Maske aus dem alten Projekt. Für M001 wollen wir den Speckig-Tree als Startseite.

## Done when
- `app/index.php` rendert ein leeres Skelett mit `<main><h1>speckig</h1></main>`, ohne Login-Logik.
- Die alten Login-Files (`app/user/`, `app/index_mobile.php`) bleiben unangetastet, werden aber von `/` nicht mehr aufgerufen.
- README erwähnt den Startbefehl `php -S 127.0.0.1:8080 -t app` in einer Zeile.
