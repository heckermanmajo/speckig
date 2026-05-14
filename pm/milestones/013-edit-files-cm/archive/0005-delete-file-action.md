# 0005 — Aktion "Datei loeschen"

"Loeschen"-Button neben dem Edit-Button im Code-Tab-Toolbar. JS-
`confirm()`-Prompt. POST `?action=delete_file&path=...`. Nur Dateien,
keine Verzeichnisse, Schwarzliste greift.

## Done when
- `app/file.php` neuer POST-Pfad `?action=delete_file`:
  - `?path=...`-Validierung wie Save (SPECKIG_ROOT, Schwarzliste).
  - `is_file($abs)` true — sonst 400 `Pfad ist keine Datei.`.
  - `unlink($abs)` — bei Fehler 500.
  - Antwort `{"ok":true,"path":...}`.
- `app/_share/js/content_loader.js`: zusaetzlicher Button
  `<button class="btn-delete">Delete</button>` in der Toolbar des
  Code-Tabs, neben Edit. Sichtbar nur wenn `current_editable` true.
- Click-Handler `on_delete_click`:
  - `confirm("Diese Datei wirklich loeschen?")` — bei Abbruch return.
  - Fetch POST.
  - Bei `ok:true`: `show_initial_placeholder()` aufrufen (Content-
    Panel zurueck auf Platzhalter) und `window.location.reload()`
    fuer Tree-Refresh.
  - Bei Fehler: `.toolbar-error` mit Server-Message.
- Editor-Modus ueberschreibt `btn-delete` nicht — d.h. wenn `edit_is_active`,
  ist der Delete-Button hidden (Save/Cancel sind dann sichtbar).

## Verifikation
- `php -l app/file.php` clean.
- Server 8086.
- **Setup**: `curl -s -X POST -H 'Content-Type: application/json' -d '{"dir":"app/_share/css","name":"delete-smoke.css"}' 'http://127.0.0.1:8086/file.php?action=new_file'` → ok:true.
- **Happy path**:
  - `curl -s -X POST 'http://127.0.0.1:8086/file.php?action=delete_file&path=app/_share/css/delete-smoke.css'` → `"ok":true`.
  - `ls app/_share/css/delete-smoke.css` schlaegt fehl.
- **Guards (400)**:
  - Verzeichnis: `path=app/_share/css` → 400 `Pfad ist keine Datei.`.
  - Vendor-Datei: `path=app/_share/vendor/Parsedown.php` → 400.
  - .git: `path=.git/HEAD` → 400.
  - Existiert nicht: `path=app/_share/css/no-such.css` → 400.
  - Traversal: `path=../../etc/passwd` → 400.
- **Markup**: `grep -c 'btn-delete' app/_share/js/content_loader.js` ≥ 1.
- **Regression**: Save-Endpoint und new_file-Endpoint bleiben funktional.
- `git status` clean nach allen Tests.

## Out of scope
- Bulk-Delete.
- Trash / Undo.
- Verzeichnisse loeschen.
- Undo-Buffer im Editor leeren bei Delete.

## Done
- `app/file.php`: neuer POST-Pfad `?action=delete_file&path=...` direkt nach
  dem `new_file`-Dispatch (vor dem GET-Pfad). Pfad-Validierung in Schichten
  mit benannten Bools (`del_*`-Prefix):
  - String-Check: nicht leer, kein `..`, kein fuehrender `/`. Sonst 400
    `Ungueltiger Pfad.`.
  - Schwarzliste analog Save-Handler: Prefix UND Substring fuer
    `app/_share/vendor/`, `.git/`, `app/_share/spec_parser/`. Treffer ->
    400 `Pfad nicht editierbar.`.
  - `realpath($speckig_root_abs . "/" . $del_raw_path)` muss aufloesen und
    via `str_starts_with($del_target_abs, $speckig_root_abs . DIRECTORY_SEPARATOR)`
    innerhalb der Repo-Wurzel liegen. Sonst 400 `Ungueltiger Pfad.`. Damit
    fallen nicht-existierende Pfade hier raus (realpath -> false) — der
    `is_file`-Check unten greift nur fuer existierende Pfade, die aber
    Verzeichnisse sind.
  - `is_file($del_target_abs)` false (Verzeichnis o.ae. — Datei waere durch
    den realpath-Check schon gefiltert) -> 400 `Pfad ist keine Datei.`.
- `@unlink($del_target_abs)` -> bei false 500 `Loeschen fehlgeschlagen.`,
  sonst 200 + `{ok:true, path}`. unlink() ist POSIX-atomar (Verzeichnis-
  eintrag verschwindet entweder oder nicht); ein tmp+rename-Tanz waere
  hier unsinnig — bewusst keine "Atomic"-Schicht.
- Jede Abweisung loggt via `app::error_log()`; der Erfolg loggt ebenfalls
  (analog new_file-Handler).
- Spec-Block am Dateianfang um den `delete_file`-Eintrag ergaenzt;
  Inline-`@spec` direkt ueber dem Handler dokumentiert den Vertrag.
  Hinweis-Kommentar erwaehnt, dass die Schwarzliste jetzt VIERFACH inline
  lebt (Save / GET-editable / new_file / delete_file) und Konsolidierung
  Folge-Ticket bleibt.
- `app/_share/js/content_loader.js`:
  - Spec-Block oben um die Delete-Action ergaenzt — Toolbar-Position
    (parallel zum Edit-Button, hidden im Edit-Modus), Confirm-Dialog,
    Reload bei Erfolg, `.toolbar-error` bei Fehler.
  - `render_code_toolbar()` haengt einen vierten Button `.btn-delete`
    zwischen Edit und Save in die Toolbar. Initialer Sichtbarkeitszustand
    kommt aus dem ersten `toggle_toolbar_buttons()`-Aufruf nicht — wir
    rendern den Delete-Button OHNE `hidden=true`, weil `edit_is_active`
    nach Load false ist und Delete dann sichtbar sein soll.
  - `toggle_toolbar_buttons(toolbar_node)` setzt `btn_delete.hidden`
    parallel zu `btn_edit.hidden` (defensiver Null-Check, damit aeltere
    Render-Pfade ohne Delete-Button nicht crashen).
  - Neuer Handler `on_delete_click()`: `window.confirm("Diese Datei
    wirklich loeschen?")`, bei Abbruch return; sonst POST an
    `/file.php?action=delete_file&path=...`. try/catch um fetch UND
    `response.json()`; Fehlerschema spiegelt das `on_save_click()`-Pattern
    (`.toolbar-error` ueber der Toolbar einhaengen). Bei Erfolg
    `show_initial_placeholder()` (setzt den Modul-State zurueck) +
    `window.location.reload()` fuer Tree-Refresh.
  - Helper `show_delete_error(code_panel, toolbar_node, message)`
    extrahiert das Error-DOM-Building (analog `show_new_file_error()`
    aus M013/0004).
- `app/_share/css/app.css`: eine Mini-Regel `.btn-delete { color: #c00; }`
  am Ende — destruktive Aktion visuell sofort erkennbar. Confirm-Dialog
  bleibt die eigentliche Schutzschicht; die Farbe ist nur Hinweis.
- Stil: BSD-Klammern, `snake_case`, `$what_cond_means`-Pattern (`del_*`-,
  `btn_delete_exists`-, `delete_ok`-Bools), `app::error_log()` statt
  direktem `error_log()`.

Files touched:
- `app/file.php` (+~140 Zeilen Spec + Handler).
- `app/_share/js/content_loader.js` (+~160 Zeilen Spec/Handler/Helper).
- `app/_share/css/app.css` (+8 Zeilen).
- `pm/milestones/013-edit-files-cm/milestone.md` (Haekchen + Pfad).
- Ticket selbst nach `archive/`.

Smoketest-Belege (Server 8086, 8083 unangetastet):
- Setup new_file `app/_share/css/delete-smoke.css` -> 200 + ok:true, Datei
  vorhanden (0 bytes).
- Happy path delete `app/_share/css/delete-smoke.css` -> 200 +
  `{"ok":true,"path":"app\/_share\/css\/delete-smoke.css"}`. `ls`
  schlaegt fehl.
- Guards (alle 400 + ok:false):
  - Verzeichnis `app/_share/css` -> `Pfad ist keine Datei.`.
  - Vendor `app/_share/vendor/Parsedown.php` -> `Pfad nicht editierbar.`.
  - .git `/.git/HEAD` -> `Pfad nicht editierbar.`.
  - spec_parser `app/_share/spec_parser/spec_parser.php` -> `Pfad nicht
    editierbar.`.
  - Nicht existent `app/_share/css/no-such.css` -> `Ungueltiger Pfad.`
    (realpath-Schicht greift).
  - Traversal `../../etc/passwd` -> `Ungueltiger Pfad.` (String-Schicht
    greift, realpath wird gar nicht erst aufgerufen).
- Markup-Counts: `btn-delete` x3, `on_delete_click|delete_file` x5,
  `window.confirm` x2.
- Save-Regression: POST `--data '/* x */'` auf `app/_share/css/app.css`
  -> ok:true; Restore via cp, `git diff` nur die intendierten 8
  `.btn-delete`-Zeilen.
- new_file-Regression: POST `{dir:"app/_share/css",name:"reg-smoke.css"}`
  -> ok:true. Cleanup via Delete-Endpoint -> ok:true. Datei weg.
- Routes weiter 200: `/index.php`, `/plan.php`, `/info.php`.
- Streu-File-Check: nur kanonisches `./app.sqlite`; keine `*.tmp.*`-
  oder `*-smoke.*`-Files. Server 8086 via `TaskStop` sauber beendet;
  8083 nicht angetastet.

Browser-Smoketest ist Sache des Users.
