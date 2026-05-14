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
