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
