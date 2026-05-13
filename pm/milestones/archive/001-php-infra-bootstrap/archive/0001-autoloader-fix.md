# 0001 — Autoloader & Layout fix

See: pm/decisions/0002-php-infra.md

Der einkopierte Code referenziert Namespace `_share\…`, der Ordner heisst aber `_src/`. So lädt nichts.

## Done when
- Ordner `app/_src/` ist umbenannt zu `app/_share/`.
- `app/_share/init.php` (vorher `_src/init.php`) wird von Einstiegspunkten korrekt geladen.
- `php -S 127.0.0.1:8080 -t app` startet, `/` antwortet 200 (Inhalt egal in diesem Ticket).
- Kein „class not found" im Error-Log beim Aufruf von `/`.

## Done
- `git mv app/_src app/_share` — alle 17 PHP-Files mit `100%`-Similarity als Rename erkannt.
- Keine Code-Änderungen nötig: alle `namespace _share\…`, `use _share\…` und `include "/_share/init.php"` waren schon konsistent.
- Verifiziert: `php -S 127.0.0.1:8080 -t app` liefert HTTP 200 auf `/`, Autoloader greift.
- Beobachtet (out of scope für 0001, gehört zu 0003): `init.php` ruft beim Bootstrap `db::get_by_id(User::class, 1)` — solange die `User`-Tabelle fehlt, kommt eine PDOException.
- Milestone-Status auf `active` gesetzt.
