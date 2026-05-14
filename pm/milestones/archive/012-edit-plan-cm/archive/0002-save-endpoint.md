# 0002 — pm.php um Save-Endpoint erweitern

`POST /pm.php?action=save&path=...` schreibt den Body in die Datei
unter `pm/`. Read-Pfad bleibt unveraendert.

## Done when
- `app/pm.php` reagiert auf POST mit `?action=save`:
  - `path` muss mit `pm/` beginnen, kein `..`, kein fuehrender `/`,
    Endung `.md`, realpath innerhalb des Repo-Roots — gleiche Schichten
    wie der GET-Pfad.
  - Body: raw `text/plain` (oder beliebig — wir lesen
    `file_get_contents("php://input")` und ignorieren Content-Type).
  - Max 1 MB body; sonst 413 + JSON-Fehler.
  - Schreib atomar: `tmp = path.tmp.<random>`, `file_put_contents(tmp, body)`,
    `rename(tmp, path)`.
  - Antwort 200 + `{ok:true, path}` bzw. 400 + `{ok:false, message}`.
- GET-Pfad bleibt 1:1 funktional (Smoketest aus M011 bleibt gruen).
- Schreib-Pfad lehnt alles unter `archive/` ab (defensiv: `str_contains
  $raw_path, "/archive/"` → 400). Begruendung steht im Spec-Block.
- Spec-Block am Dateianfang nennt POST-Aktion explizit.
- `app::error_log()` bei jeder Abweisung mit Pfad und Grund.

## Verifikation
- `php -l app/pm.php` ok.
- Server auf 8086.
- `curl -s -o /dev/null -w "%{http_code}" -X POST --data 'test' 'http://127.0.0.1:8086/pm.php?action=save&path=pm/ideas/save-endpoint-smoke.md'` → 200.
- `cat pm/ideas/save-endpoint-smoke.md` zeigt `test`. Danach Datei
  wieder loeschen.
- `curl -s -X POST --data 'x' 'http://127.0.0.1:8086/pm.php?action=save&path=pm/foo/../etc/passwd'` → 400, body enthaelt `Ungueltiger Pfad`.
- `curl -s -X POST --data 'x' 'http://127.0.0.1:8086/pm.php?action=save&path=pm/milestones/archive/006-plan-view/milestone.md'` → 400.
- `curl -s -X POST --data 'x' 'http://127.0.0.1:8086/pm.php?action=save&path=app/init.php'` → 400.
- GET-Smoketest aus M011 bleibt gruen.

## Out of scope
- Backup / Versionierung vor Ueberschreiben.
- Lock / If-Match-Header.
- Diff-Antwort.
- Save fuer Pfade ausserhalb `pm/` (M013).

## Done
- `app/pm.php` um Method-Dispatch erweitert: `POST ?action=save&path=...`
  laeuft VOR dem GET-Pfad, GET bleibt 1:1.
- Pfad-Whitelist identisch zu GET (`pm/`-Prefix, kein `..`, kein
  fuehrender `/`, Endung `.md`) plus zwei Save-spezifische Schichten:
  - `str_contains($raw_path, "/archive/")` -> 400 vor allem anderen.
  - parent-realpath muss existieren und innerhalb Repo-Root liegen
    (`realpath(dirname(...))`), das Target selbst darf neu sein.
- Body via `file_get_contents("php://input")`, Content-Type ignoriert,
  Limit 1 MB (1048576 B) -> sonst HTTP 413 + JSON.
- Atomar via `tmp = <target>.tmp.<bin2hex(random_bytes(4))>` +
  `file_put_contents` + `rename`; bei Fehler tmp-cleanup + 500.
- Antwort 200 + `{ok:true, path, bytes}` bei Erfolg, sonst
  `{ok:false, message}` mit passendem Status (400/413/500).
- Jede Abweisung loggt via `app::error_log()` mit Pfad und Grund.
- Spec-Block am Dateianfang nennt jetzt POST-Aktion explizit; ein
  zusaetzlicher `@spec` direkt ueber dem Save-Block dokumentiert den
  Vertrag.
- BSD-Klammern, snake_case, `$what_cond_means`-Pattern wie im
  bestehenden GET-Block.

Files touched:
- `app/pm.php` (+167 / -1, Method-Dispatch ergaenzt)
- `pm/milestones/012-edit-plan-cm/milestone.md` (Haekchen + Pfad)
- ticket selbst nach `archive/`.

Smoketest-Belege:
- GET `pm/ideas/talk_about_code_base.md` -> 1x `"ok":true`.
- POST neu (`save-endpoint-smoke.md`) -> 200, `bytes:10`, Inhalt
  `hallo welt`, danach geloescht.
- POST overwrite (`talk_about_code_base.md`) -> 200, `bytes:14`,
  zurueckkopiert, `git diff` leer.
- Guards Traversal / Wrong-Prefix / Wrong-Ext / Archive -> alle 400 +
  `Ungueltiger Pfad.`; archivierte Datei `git diff` leer.
- 1.1 MB Body -> 413 + `Body zu gross.`, kein `big-smoke.md` entstanden.
- Streu-Files: nur `./app.sqlite`; keine `*.tmp.*` in `pm/`.
