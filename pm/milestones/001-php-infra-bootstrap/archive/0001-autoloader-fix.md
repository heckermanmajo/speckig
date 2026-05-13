# 0001 — Autoloader & Layout fix

See: pm/decisions/0002-php-infra.md

Der einkopierte Code referenziert Namespace `_share\…`, der Ordner heisst aber `_src/`. So lädt nichts.

## Done when
- Ordner `app/_src/` ist umbenannt zu `app/_share/`.
- `app/_share/init.php` (vorher `_src/init.php`) wird von Einstiegspunkten korrekt geladen.
- `php -S 127.0.0.1:8080 -t app` startet, `/` antwortet 200 (Inhalt egal in diesem Ticket).
- Kein „class not found" im Error-Log beim Aufruf von `/`.
