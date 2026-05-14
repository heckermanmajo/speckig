# 0002 — CodeMirror-Modes vendoren + Extension-Mapping

Sieben zusaetzliche CodeMirror-5-Modes vendoren und ein
JS-Helper-Mapping `extension_to_mode()` in `editor.js` ergaenzen.

## Done when
- Sieben Vendor-Files unter `app/_share/vendor/js/codemirror-modes/`:
  - `php.js`     ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/php/php.js`
  - `javascript.js` ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/javascript/javascript.js`
  - `clike.js`   ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/clike/clike.js`
  - `shell.js`   ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/shell/shell.js`
  - `css.js`     ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/css/css.js`
  - `xml.js`     ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/xml/xml.js`
  - `yaml.js`    ← `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/yaml/yaml.js`
- **WICHTIG**: PHP-Mode haengt von xml, javascript, css, clike, htmlmixed
  ab. Pruefe Upstream-Dokumentation; falls htmlmixed gebraucht wird,
  vendoren wir den auch. (Subagent: download htmlmixed.js zusaetzlich,
  falls die obigen Files es per `require` referenzieren.)
- MIT-Originalheader bleibt.
- `app/_share/js/editor.js` neuer Helper `extension_to_mode(extension)`:
  - Mapping (lowercase, ohne Punkt):
    - `md`, `markdown` → `"markdown"`
    - `php`, `phtml`   → `"application/x-httpd-php"`
    - `js`, `ts`, `tsx`, `jsx`, `mjs` → `"javascript"`
    - `lua`, `nim`, `groovy`, `java`, `c`, `cpp`, `h`, `cs` → `"text/x-csrc"` (clike)
    - `sh`, `bash`, `zsh` → `"shell"`
    - `css`               → `"css"`
    - `xml`, `html`, `htm` → `"xml"` (oder `text/html` falls htmlmixed vendored)
    - `yaml`, `yml`       → `"yaml"`
    - sonst → `null` (Editor mountet im Plaintext-Mode).
  - Funktion exposed via `window.speckig_editor.extension_to_mode`.
- `mount()` in editor.js akzeptiert weiteren Parameter `mode_name`
  (Default `"markdown"` fuer Rueckwaertskompatibilitaet). Wenn `null` /
  leer: kein `mode`-Option (Plaintext).
- `pm/decisions/0007-editor-vendoring.md` bekommt einen ergaenzenden
  Bullet: "Mit M013 zusaetzlich vendored: php, javascript, clike,
  shell, css, xml, yaml (+ htmlmixed falls Dependency)." — append-only,
  nicht alte Zeilen aendern.

## Verifikation
- `node --check app/_share/js/editor.js` clean.
- Server 8086. Curl `-sI` auf alle 7 (bzw. 8) URLs → 200.
- `head -3` auf jedem Mode-File zeigt CodeMirror-Copyright.
- Mapping-Spotcheck via DevTools:
  - `speckig_editor.extension_to_mode("php")` === `"application/x-httpd-php"`.
  - `speckig_editor.extension_to_mode("ts")`  === `"javascript"`.
  - `speckig_editor.extension_to_mode("lua")` === `"text/x-csrc"`.
  - `speckig_editor.extension_to_mode("xyz")` === `null`.
  Subagent: Curl reicht — Browser-Smoketest ist Sache des Users; alternativ:
  `node -e "..."` falls trivial moeglich. Optional skippen, wenn unklar.
- `mount()`-Backwards-Compat: M012's Plan-View laedt weiter sauber
  (mode default markdown). Curl `/info.php` und `/plan.php` → 200.

## Out of scope
- Mode-Lazy-Load via dynamic script-tag — alle Modes werden statisch
  eingebunden (folgt in 0003).
- Theme-Files / Linter / Folder-Plugins.

## Done
- Acht neue Vendor-Files unter `app/_share/vendor/js/codemirror-modes/`,
  alle 1:1 von `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/`
  per `curl -sSL`, MIT-Originalheader unveraendert:
  - `php.js` (18339 B)
  - `javascript.js` (38894 B)
  - `clike.js` (37362 B)
  - `shell.js` (5383 B)
  - `css.js` (40492 B)
  - `xml.js` (13353 B)
  - `yaml.js` (3734 B)
  - `htmlmixed.js` (5688 B) — `php.js` referenziert per `require`
    `../htmlmixed/htmlmixed`, also vendored mit. Weitere Querverweise
    aus dem Set bleiben innerhalb der jetzt vendored Modes (htmlmixed
    zieht xml, javascript, css — alle vorhanden).
- `app/_share/js/editor.js`:
  - Neue Funktion `extension_to_mode(extension)` mit dem im Ticket
    spezifizierten Mapping (lowercase, fuehrender Punkt wird gestripped),
    `null` als Fallback fuer unbekannte/leere/non-string Inputs.
  - `mount()` neue Signatur `mount(article_element, raw_markdown, path,
    mode_name)`. `mode_name === undefined` ⇒ Fallback `"markdown"`
    (Backwards-Compat fuer Plan-/Info-View). `mode_name === null` oder
    `""` ⇒ kein `mode`-Option ⇒ Plaintext. Sonst wird der Wert direkt
    an `CodeMirror.fromTextArea` durchgereicht.
  - `window.speckig_editor.extension_to_mode` exposed.
  - Spec-Block oben aktualisiert (mehrere Modes, neue Signatur,
    Helper).
- `pm/decisions/0007-editor-vendoring.md` um eine Zeile ergaenzt
  (append-only): "Mit M013 zusaetzlich vendored: php, javascript,
  clike, shell, css, xml, yaml, htmlmixed (Dependency von php).".
- Smoketests (`php -S 127.0.0.1:8086 -t app`):
  - Alle neun Mode-Files (incl. markdown aus 0001) liefern
    `HTTP/1.1 200 OK` auf `/_share/vendor/js/codemirror-modes/<m>.js`.
  - `plan.php` und `info.php` liefern weiter 200, beide referenzieren
    weiterhin `editor.js`.
- Node-Mapping-Smoketest (Eval-Harness mit `global.window = {}`)
  laeuft mit Exit-Code 0 ueber 10 Faelle (php, ts, lua, md, css,
  yaml, sh, xml, xyz, "").
- `node --check app/_share/js/editor.js` clean.
- Streu-File-Check: nur kanonisches `./app.sqlite`.

See: pm/decisions/0007-editor-vendoring.md
