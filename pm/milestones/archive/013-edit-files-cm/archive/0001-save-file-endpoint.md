# 0001 — file.php Save-Endpoint mit SPECKIG_ROOT-Scope

`POST /file.php?action=save&path=...` schreibt den Body in die Datei
unter SPECKIG_ROOT. GET-Pfad bleibt unveraendert.

## Done when
- `app/file.php` Method-Dispatch ganz oben:
  - `REQUEST_METHOD === "POST"` und `?action=save` → Save-Handler.
  - Sonst weiter zum GET-Pfad.
- **Pfad-Validierung** (alle Schichten):
  - kein `..`, kein fuehrender `/`, `realpath`(parent) muss innerhalb
    von SPECKIG_ROOT liegen (Schreib auf nicht-existierende Files
    erlaubt).
  - Schwarzliste: Pfad darf nicht beginnen mit (oder enthalten):
    `app/_share/vendor/`, `.git/`, `app/_share/spec_parser/`.
    Bei Treffer → 400 `Pfad nicht editierbar.`.
  - Wenn die Datei existiert: Binary-Guard greift (siehe unten).
- **Binary-Guard**: erste 8 KB der Body pruefen — wenn ein `\0`-Byte
  drin ist → 400 `Binaere Inhalte nicht erlaubt.`. Begruendung als
  Spec-Comment.
- **Body-Limit**: `strlen($body) > 1048576` → 413 `Body zu gross.`.
- **Atomar schreiben**: tmp + rename wie M012/0002.
- Antwort 200 + `{"ok":true,"path":...,"bytes":...}` bzw. 400/413/500.
- `app::error_log()` bei jeder Abweisung mit Pfad und Grund.
- Spec-Block am Dateianfang erweitert.

## Verifikation
- `php -l app/file.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- GET-Pfad bleibt funktional:
  `curl -s 'http://127.0.0.1:8086/file.php?path=README.md' | grep -c '"ok":true'` ≥ 1
  (oder `pm/how-to/process.md` falls README.md nicht existiert).
- **Happy path**:
  - `TESTFILE=app/_share/css/save-smoke.css` (neue Datei in einem
    erlaubten Verzeichnis).
  - `curl -s -X POST --data '/* test */' "http://127.0.0.1:8086/file.php?action=save&path=$TESTFILE"` → `200` + `"ok":true`.
  - `cat $TESTFILE` zeigt `/* test */`. Cleanup: `rm $TESTFILE`.
- **Existierende Datei ueberschreiben + restaurieren** (analog M012/0002):
  - Sicherung `cp pm/ideas/talk_about_code_base.md /tmp/restore.bak`.
  - POST mit `overwrite-test`. `cat` zeigt es. Restore. `git diff` leer.
- **Guards (400)**:
  - Traversal: `path=../etc/passwd` → 400.
  - Vendor: `path=app/_share/vendor/Parsedown.php` → 400.
  - Vendor JS: `path=app/_share/vendor/js/codemirror.min.js` → 400.
  - .git: `path=.git/HEAD` → 400.
  - spec_parser: `path=app/_share/spec_parser/spec_parser.php` → 400.
- **Binary-Guard**:
  - `printf 'before\\0after' | curl -s -w '\\n%{http_code}\\n' --data-binary @- "http://127.0.0.1:8086/file.php?action=save&path=app/_share/css/binary-smoke.css"` → 400.
  - `ls app/_share/css/binary-smoke.css` schlaegt fehl (Datei nicht angelegt).
- **Body-Limit (413)**:
  - 1.1 MB body → 413, Datei wird nicht angelegt.
- `find . -name "*.tmp.*" -not -path "./.git/*"` leer.
- Server stoppen, 8083 nicht anfassen.
- `git status` final clean.

## Out of scope
- new_file / delete_file / Toolbar — eigene Tickets.
- Rename / Move.
- Encoding-Detection.
- Conflict-Resolution.

## Done
- `app/file.php` um Method-Dispatch ergaenzt: `POST ?action=save&path=...`
  laeuft VOR dem GET-Pfad, GET bleibt 1:1 funktional. Der Dispatch sitzt
  direkt nach der `$speckig_root_abs`-Resolution, damit der Save-Handler
  diesen wiederverwenden kann.
- Pfad-Validierung in Schichten:
  - String-Check: nicht leer, kein `..`, kein fuehrender `/`.
  - Schwarzliste: `app/_share/vendor/`, `.git/`, `app/_share/spec_parser/`,
    je als Prefix UND als Substring (defensive Schicht gegen Symlinks /
    eingeschachtelte Layouts) -> 400 `Pfad nicht editierbar.`.
  - parent-realpath muss existieren und innerhalb `$speckig_root_abs`
    liegen; Sonderfall `parent_rel === "."` (Datei im Repo-Root) wird
    direkt erlaubt; Parent muss `is_dir`.
- Body via `file_get_contents("php://input")`, Content-Type ignoriert;
  Limit 1 MB (1048576 B) -> 413 + `Body zu gross.`.
- Binary-Guard: `str_contains(substr($body, 0, 8192), "\0")` -> 400 +
  `Binaere Inhalte nicht erlaubt.`. Verhindert versehentliche Zerstoerung
  von Binaerdateien ueber den Editor, ohne UTF-8-Validierung zu bauen.
- Atomar via `tmp = <target>.tmp.<bin2hex(random_bytes(4))>` +
  `file_put_contents` + `rename`; bei Fehler tmp-cleanup + 500.
- Antwort 200 + `{ok:true, path, bytes}` bei Erfolg, sonst
  `{ok:false, message}` mit passendem Status (400/413/500).
- Jede Abweisung loggt via `app::error_log()` mit Pfad und Grund.
- Spec-Block am Dateianfang erweitert (GET + POST), zusaetzlicher
  `@spec` direkt ueber dem Save-Block dokumentiert den Vertrag.
- BSD-Klammern, snake_case, `$what_cond_means`-Pattern wie im
  bestehenden GET-Block.

Files touched:
- `app/file.php` (+216 / -1, Method-Dispatch + Save-Handler ergaenzt)
- `pm/milestones/013-edit-files-cm/milestone.md` (Haekchen + Pfad)
- ticket selbst nach `archive/`.

Smoketest-Belege:
- GET `app/_share/css/app.css` -> 1x `"ok":true`. GET
  `pm/how-to/process.md` -> 1x `"ok":true`.
- POST happy `app/_share/css/save-smoke.css` -> 200, `bytes:18`, Inhalt
  `/* claude smoke */`, danach geloescht (nicht in `git status`).
- POST overwrite `pm/ideas/talk_about_code_base.md` -> 200, `bytes:18`,
  zurueckkopiert, `git diff` leer.
- Guards (alle 400): Traversal, Vendor PHP, Vendor JS, .git/HEAD,
  spec_parser, mid-`..`. Vendor/Git/Spec_parser werfen
  `Pfad nicht editierbar.`, der Rest `Ungueltiger Pfad.`.
- Binary-Guard: NUL-Byte in den ersten 8 KB -> 400 +
  `Binaere Inhalte nicht erlaubt.`; `bin-smoke.css` nicht entstanden.
- Body-Limit: 1.1 MB Body -> 413 + `Body zu gross.`; `big-smoke.css`
  nicht entstanden.
- pm.php-Save (M012/0002) bleibt funktional: POST `via-pm-php` ->
  200, restore, `git diff` leer.
- Streu-Files: nur `./app.sqlite`; keine `*.tmp.*`.
