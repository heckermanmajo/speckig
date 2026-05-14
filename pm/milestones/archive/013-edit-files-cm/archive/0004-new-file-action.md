# 0004 — Aktion "+ Datei" pro Tree-Ordner

Pro Ordner-`<details>`-Block im Tree ein "+"-Button mit Inline-Form
fuer Dateinamen. Submit ruft `POST /file.php?action=new_file&dir=...`
und legt eine leere Datei an. Tree wird reloaded.

## Done when
- `app/file.php` neuer POST-Pfad `?action=new_file`:
  - Body JSON: `{dir, name}`.
  - `dir` muss innerhalb SPECKIG_ROOT liegen (gleiche Validation wie
    Save-Endpoint, plus `is_dir`). Schwarzliste greift (kein
    vendor/.git/spec_parser).
  - `name`: 1–120 Zeichen, nur `[A-Za-z0-9._-]`, kein fuehrender Punkt
    (keine versteckten Dateien anlegen), kein Slash.
  - Bei Kollision (Datei existiert schon) → 409 `Datei existiert
    bereits.`.
  - Leere Datei via `file_put_contents($path, "")` (Folder existiert).
  - Antwort `{"ok":true,"path":"<dir>/<name>"}`.
- `app/index.php` rendert pro `<details>`-Block einen
  `<button class="btn-new-file" data-dir="<rel>">+</button>` direkt im
  `<summary>` (oder als erstes Child im `<details>`, vor den Sub-
  Eintraegen). Plus verstecktes `<form class="new-file-form"
  data-dir="<rel>">` mit `<input name="name">` + Submit/Cancel.
- `app/_share/js/content_loader.js` oder neue `tree_actions.js`:
  - Click-Handler fuer `.btn-new-file`: zugehoeriges Form anzeigen,
    Button hidden.
  - `.btn-cancel-form`: Form hidden, Button sichtbar, leer.
  - Submit: fetch POST, bei Erfolg `window.location.reload()`.
- Bei Schwarzliste-Ordnern (vendor / .git / spec_parser) wird der
  Button **gar nicht** im Tree gerendert. Heuristik in `render_tree()`
  in index.php: wenn `$rel_prefix` mit einem der drei Pfade beginnt,
  kein Button. Begruendung als PHP-Kommentar.

## Verifikation
- `php -l app/file.php app/index.php` clean.
- `node --check app/_share/js/content_loader.js` clean (oder die neue
  tree_actions.js, falls separat).
- Server 8086.
- **Happy path**:
  - `curl -s -X POST -H 'Content-Type: application/json' -d '{"dir":"app/_share/css","name":"new-smoke.css"}' 'http://127.0.0.1:8086/file.php?action=new_file'` → `"ok":true`.
  - `ls app/_share/css/new-smoke.css` listet die Datei (0 bytes).
  - Cleanup: `rm app/_share/css/new-smoke.css`.
- **Guards (400)**:
  - `name` mit Slash → `"ok":false`.
  - `name` mit fuehrendem Punkt (`.hidden`) → `"ok":false`.
  - `name` leer → `"ok":false`.
  - `name` 121 Zeichen → `"ok":false`.
  - `dir` ausserhalb Repo → `"ok":false`.
  - `dir` ist Vendor → `"ok":false`.
  - `dir` ist Datei statt Ordner → `"ok":false`.
- **409**: gleicher Name zweimal → erst `"ok":true`, dann `"ok":false`
  mit `Datei existiert bereits.`. Cleanup.
- **Markup**:
  - `curl -s 'http://127.0.0.1:8086/index.php' | grep -c 'btn-new-file'` ≥ 1
    (Tree rendert die Buttons).
  - Auf Vendor-Ordner kein Button: `curl -s 'http://127.0.0.1:8086/index.php' | grep -o 'btn-new-file[^"]*data-dir="[^"]*"' | grep -c 'app/_share/vendor'` → 0.

## Out of scope
- Template-Inhalt bei Anlage (immer leer).
- Sofortiges Oeffnen der neuen Datei im Editor.
- Datei in noch nicht existendem Unterordner anlegen.
- Rename / Move.

## Done
- `app/file.php`: neuer POST-Pfad `?action=new_file` direkt nach dem
  Save-Dispatch. Body via `file_get_contents("php://input")` + `json_decode`;
  Parse-Fehler oder kein-Objekt -> 400 `Body ist kein JSON.`.
- Payload-Validierung in Schichten mit benannten Bools (`nf_*`-Prefix):
  - `dir`: String, leer = Repo-Root, sonst kein `..`, kein fuehrender `/`.
  - Schwarzliste analog Save: Prefix + Substring fuer `app/_share/vendor/`,
    `.git/`, `app/_share/spec_parser/`; zusaetzlich exakte Treffer
    `"app/_share/vendor"`, `".git"`, `"app/_share/spec_parser"` ohne
    Trailing-Slash, sonst koennten die ohne `/` durchschluepfen.
  - `dir_abs = realpath($speckig_root_abs . "/" . $dir)`, Sonderfall
    `dir === ""` -> `dir_abs = $speckig_root_abs`; muss innerhalb Root
    liegen UND `is_dir`. Sonst 400 `Ordner nicht gefunden.`.
  - `name`: 1-120 Zeichen, Regex `^[A-Za-z0-9._-]+$`, kein fuehrender
    Punkt, kein Slash, kein `..`. Sonst 400 `Ungueltiger Dateiname.`.
- Kollisionspruefung `file_exists($dir_abs . "/" . $name)` -> 409
  `Datei existiert bereits.`. Schreiben via `file_put_contents(.., "")`;
  bei false -> 500 `Anlegen fehlgeschlagen.`.
- Antwort 200 + `{ok:true, path:"<dir>/<name>"}` bzw. `{ok:true, path:"<name>"}`
  falls `dir` leer. Jede Abweisung loggt via `app::error_log()`; Erfolg
  loggt ebenfalls.
- Spec-Block am Dateianfang erweitert (`POST ?action=new_file`-Eintrag);
  Inline-`@spec` ueber dem Handler dokumentiert den Vertrag. Hinweis-
  Kommentar erwaehnt explizit, dass die Schwarzliste jetzt dreifach
  inline lebt (Save / GET-editable / new_file) und Konsolidierung in
  einen Helper Folge-Ticket ist.
- `app/index.php`: `render_tree()` rendert pro Sub-Dir-`<details>` einen
  `<div class="tree-action-block">` mit `.btn-new-file`, initial `hidden`em
  `.new-file-form` (Input `.input-name`, `.btn-submit`, `.btn-cancel-form`,
  `.form-error`) — alle tragen `data-dir="<rel-pfad>"`. Schwarzliste
  per `str_starts_with($sub_dir_rel_path, "app/_share/vendor" | ".git" |
  "app/_share/spec_parser")` -> kein Button. Zusatz-Block fuer Repo-Root
  (`data-dir=""`) im `<nav>`, VOR dem Tree, mit Label `+ Datei (Root)`.
- `app/_share/js/content_loader.js`: Spec-Block oben um New-File-Action
  ergaenzt. Neue Funktionen `show_new_file_error`,
  `on_new_file_button_click`, `on_new_file_cancel_click`,
  `on_new_file_submit`, `init_new_file_forms`. Pairing via `closest(
  ".tree-action-block")` -> `.new-file-form` und `.btn-new-file`,
  damit `data-dir=""` (Root) nicht mit anderen Forms kollidiert.
  Selektoren INNERHALB einer Form laufen relativ
  (`form.querySelector(".form-error")`), damit nichts in plan_loader-Forms
  bleedet. Submit POSTet `{dir, name}` als JSON, try/catch um fetch UND
  `response.json()`, bei `ok:true` `window.location.reload()`, sonst
  `.form-error` mit Server-Message oder Fallback. `init_new_file_forms`
  ist self-guarded (no-op ohne Buttons/Forms) und wird am Ende von
  `init_content_loader()` aufgerufen.
- `app/_share/css/app.css`: drei Mini-Regeln am Ende fuer
  `.tree-action-block`, `.btn-new-file`, `.new-file-form`. `.form-error`
  wird aus M012/0005 wiederverwendet.
- Stil: BSD-Klammern, `snake_case`, `$what_cond_means`-Pattern,
  `app::error_log()` statt direktem `error_log()`.

Files touched:
- `app/file.php` (+~260 Zeilen Spec/Handler).
- `app/index.php` (+~30 Zeilen Root-Block + Sub-Dir-Block).
- `app/_share/js/content_loader.js` (+~245 Zeilen Spec/Handler/init).
- `app/_share/css/app.css` (+~20 Zeilen).
- `pm/milestones/013-edit-files-cm/milestone.md` (Haekchen + Pfad).
- Ticket selbst nach `archive/`.

Smoketest-Belege (Server 8086):
- Happy path: POST `{"dir":"app/_share/css","name":"new-smoke.css"}` ->
  200 + `{"ok":true,"path":"app\/_share\/css\/new-smoke.css"}`. Datei
  vorhanden (0 bytes), danach geloescht.
- Happy path Root: POST `{"dir":"","name":"root-smoke.txt"}` -> 200 +
  `{"ok":true,"path":"root-smoke.txt"}`. Datei in Repo-Root vorhanden,
  geloescht.
- Guards (alle 400 + `ok:false`): name mit Slash, fuehrender Punkt,
  leer, Sonderzeichen `$`, dir mit `..`, dir `/tmp` (absolut), dir
  Vendor exakt, dir `.git` exakt, dir spec_parser exakt, dir ist
  Datei (`app/file.php`), Body kein JSON, name 121 Zeichen.
- 409: erste Anlage `collide-smoke.css` -> 200, zweite -> 409 +
  `Datei existiert bereits.`. Cleanup.
- Markup: `grep -o btn-new-file index.html | wc -l` = 75 (genau so
  viele wie `new-file-form` und `tree-action-block`). Daten-Dirs in
  Buttons enthalten weder `vendor` noch `spec_parser` noch `.git`.
  Root-Button (`data-dir=""`) genau einmal vorhanden.
- Routes weiter 200: `/index.php`, `/plan.php`, `/info.php`. GET
  `/file.php?path=README.md` weiter `"ok":true`.
- Streu-Files: nur kanonisches `./app.sqlite`; keine `*.tmp.*`-Files.
  Server 8086 nach Tests gestoppt; 8083 nicht angetastet.
