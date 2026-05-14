# 0003 — editor.js: IIFE-Wrapper um CodeMirror

Neuer Layer `app/_share/js/editor.js`, IIFE-Wrapper, exposed als
`window.speckig_editor`. Kein direkter `CodeMirror.fromTextArea()`-Aufruf
aus den Plan-/Info-Loadern — die rufen nur Funktionen aus diesem Layer.

## Done when
- `app/_share/js/editor.js` mit IIFE-Struktur analog zu
  `plan_loader.js`:
  - `let cm_instance = null;` als Modul-State.
  - `function mount(article_element, raw_markdown, path)`: erstellt
    `<textarea>`, mountet `CodeMirror.fromTextArea` mit Mode `markdown`
    und `lineNumbers: true`, haelt die Instanz im Modul-State.
  - `function get_value()`: liefert den aktuellen Buffer-Inhalt.
  - `function destroy()`: `cm_instance.toTextArea()`, Reset.
  - `function save(path)`: ruft `fetch("/pm.php?action=save&path=...",
    {method:"POST", body: get_value()})`, gibt Promise zurueck.
- Stil: BSD-Klammern, snake_case, `what_cond_means`, let/const, async/
  await, defensive try/catch um fetch (vgl. `plan_loader.js`).
- Spec-Block am Dateianfang erklaert: Verantwortung, was es NICHT macht
  (DOM-Layout, Buttons, Plan-Loader-Logik bleiben in den Aufrufern).
- Wird in `plan.php` und `info.php` per `<script src=
  "/_share/vendor/js/codemirror.min.js">` + `markdown`-Mode + 
  `editor.js` eingebunden, dazu `<link rel="stylesheet" href=
  "/_share/vendor/css/codemirror.css">`.
- `app/_share/css/app.css` bekommt 3 Mini-Regeln fuer die Editor-Hoehe
  (`.CodeMirror { height: auto; min-height: 60vh; }` o.ae.).

## Verifikation
- `php -l app/plan.php app/info.php` ok.
- Server 8086.
- `curl -s http://127.0.0.1:8086/plan.php | grep -c '/_share/js/editor.js'` → 1.
- `curl -s http://127.0.0.1:8086/info.php | grep -c '/_share/js/editor.js'` → 1.
- `curl -s http://127.0.0.1:8086/plan.php | grep -c 'codemirror.css'` → 1.
- Manual: Browser-Console auf `/plan.php` ausfuehren — `typeof
  speckig_editor.mount` ist `"function"`, `typeof CodeMirror` ist
  `"function"`.

## Out of scope
- Buttons / DOM-Layout im Content-Panel (das macht 0004).
- Mehrere Editor-Instanzen parallel — nur eine pro Seite.
- Save aus diesem Layer triggern — der Layer bietet `save()` als API,
  aber Aufrufer entscheidet wann.
- Andere Modes ausser Markdown.
