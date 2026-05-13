# 0005 — Speckig-Index-Seite

See: pm/decisions/0002-php-infra.md
Blocked by: 0001, 0002

`app/index.php` zeigt aktuell die Login-Maske aus dem alten Projekt. Für M001 wollen wir den Speckig-Tree als Startseite.

## Done when
- `app/index.php` rendert ein leeres Skelett mit `<main><h1>speckig</h1></main>`, ohne Login-Logik.
- Die alten Login-Files (`app/user/`, `app/index_mobile.php`) bleiben unangetastet, werden aber von `/` nicht mehr aufgerufen.
- README erwähnt den Startbefehl `php -S 127.0.0.1:8080 -t app` in einer Zeile.

## Done
- `app/index.php` neu geschrieben: `declare(strict_types=1)`, `include _share/init.php` bleibt (Autoloader/Session werden später gebraucht), Login-Formular und Inline-Script weg, `app::is_mobile`- und `app::somebody_is_logged_in`-Redirects weg.
- `document::head()` / `document::footer()` werden NICHT aufgerufen — die globale Nav rendert Login/Dashboard-Verweise, und Brand-Text "community" passt nicht zu Speckig. Eigenes minimales `<!doctype html>`-Skelett ist ehrlicher zum Done-when "ohne Login-Logik" und kürzer.
- Login-Code (`app/user/`, `app/_share/html/document.php`, `app/index_mobile.php`, `app/api.php`) unverändert; nur nicht mehr von `/` aufgerufen.
- `README.md`: Abschnitt `## Run` mit Startbefehl `php -S 127.0.0.1:8080 -t app` ergänzt.
- Verifikation: `grep -E "(LoginAction|login_form|is_mobile|somebody_is_logged_in)" app/index.php` leer. `php -l app/index.php` → "No syntax errors". Smoketest gegen `php -S 127.0.0.1:8080 -t app`: HTTP 200, Body enthält `<h1>speckig</h1>`, kein `login_form`.
- Keine Streu-`app.sqlite` ausserhalb von `/home/mo/Schreibtisch/speckig/app.sqlite`.
