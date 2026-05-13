# 0002 — Editor-Library für M004

Date: 2026-05-13
Type: comparison
Status: final

## TL;DR
- Empfehlung: **CodeMirror 5** — ein einziges `codemirror.min.js` (~200 KB) plus pro Sprache eine kleine Mode-Datei, alles via jsDelivr/cdnjs runterladbar, MIT, 110+ Sprachen, API ist eine Funktion (`CodeMirror.fromTextArea`).
- **Ace** ist solider Plan B (BSD, aktiv, 110+ Sprachen), aber das `src-min-noconflict/`-Verzeichnis kommt mit ~30+ Files und einem `ace.js` von ~362 KB — mehr Vendor-Footprint.
- **CodeMirror 6** ist ohne Bundler nur über Community-Pre-Builds (`paul-norman/codemirror6-prebuilt`, 13 Stars, last push 2025-08) brauchbar — zu fragil für unsere Vendor-Policy.
- **Monaco** raus: ~13 MB AMD-Bundle, hundert+ Files, deprecated AMD-Loader.
- Tabs baut speckig in jedem Fall selbst (eine Editor-Instanz pro Tab, kein eingebauter Multi-Doc-Manager bei einem der vier Kandidaten).

## Vergleich

| Library          | Bundle (min)        | Vendor-Files                       | Sprachen | Tabs built-in | Last Release | Lizenz | Verdict |
|------------------|---------------------|------------------------------------|---------:|:-------------:|--------------|--------|---------|
| CodeMirror 5     | ~200 KB core + ~5-20 KB pro Mode | `codemirror.min.js` + `codemirror.css` + N Mode-Files | 110+ | nein | 5.65.21 (2026-02-07) | MIT | **Empfohlen** |
| CodeMirror 6     | ~150-300 KB pro Sprach-Bundle | community pre-built `dist/*.min.js` (~20 Sprachen, je 1 File) | ~20 (im Pre-Built) | nein | core 6.42.x (2026-05) | MIT | Plan C |
| Ace              | ~362 KB `ace.js` + ~30+ Mode/Worker-Files | komplettes `src-min-noconflict/`-Dir (~3-5 MB) | 110+ | nein | 1.43.6 (2025-03-02) | BSD-3 | Plan B |
| Monaco           | ~13 MB `min/vs/` (≈3.7 MB allein für `editor.api-*.js`) | 109 Files, mehrere Unterordner | ~80+ | nein | 0.55.1 (2025-11-20) | MIT | Raus |

## Kandidaten

### CodeMirror 5 (empfohlen)
- **Stärken**: Klassisches Drop-in-Modell. `codemirror.min.js` + `codemirror.css` + ein Mode pro Sprache (`mode/php/php.js`, `mode/yaml/yaml.js`, …). Genau das Muster, das schon mit Parsedown.php funktioniert. 27.2k Stars, MIT, weiter gewartet (5.65.21 im Feb 2026).
- **Schwächen**: Offiziell „legacy"-Branding seit CM6 — aber lebt klar weiter (Patch-Release ~Feb 2026). Keine ESM-Imports, nur globales `CodeMirror`-Objekt; das ist für unseren IIFE-Stil eher Vorteil.
- **Vendoring**: pro Sprache 5-20 KB. Mit PHP, YAML, Markdown, JS, Python, Bash, SQL, JSON, HTML, CSS, Go, Rust kommen wir auf ~12 Modes → grob 100-200 KB zusätzlich. Quelle: `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/`.
- **API**:
  ```javascript
  var ed = CodeMirror.fromTextArea(document.getElementById("src"), {
      mode: "text/x-php",
      lineNumbers: true
  });
  var text = ed.getValue();
  ed.setValue("...");
  ```

### CodeMirror 6 (Plan C)
- **Stärken**: Aktiv entwickelt (Mai 2026 Releases). Moderne Architektur, accessibility, kollab-fähig.
- **Schwächen**: Offiziell nur als `@codemirror/*` ESM-Packages — **erfordert Bundler**. Workaround sind community Pre-Builds (`paul-norman/codemirror6-prebuilt`) oder esm.sh-Import-Maps. Pre-Built-Repo hat 13 Stars, last push 2025-08, ~20 Sprachen, ein File pro Sprache (`dist/php.min.js` etc.). Das ist genau die Art „one-person-vendor", die wir in unserer Policy vermeiden wollen.
- **Vendoring**: theoretisch single-file pro Sprache, praktisch fragil. Wenn der Pre-Built-Maintainer abspringt, hängen wir an einer Drittquelle, die selbst nicht offiziell ist.
- **API**: `cm6.load().textarea(el, options)` (über die Pre-Built-Hilfen) — danach normale CM6-`EditorView`-API.

### Ace (Plan B)
- **Stärken**: BSD-3, 27.1k Stars, aktiv (1.43.6 vom 2026-03 — Korrektur: 2025-03-02). 110+ Sprachen, 20+ Themes. `ace-builds`-Repo liefert direkt nutzbare Builds.
- **Schwächen**: `src-min-noconflict/` ist ein Verzeichnis-Drop-in, kein File-Drop-in: ein Hauptfile (`ace.js` ~362 KB) plus `mode-*.js`, `theme-*.js`, `worker-*.js` pro Sprache. Wir müssten ~30-50 Files einchecken oder selektiv kuratieren. Workers laufen out-of-process — bedeutet einen weiteren Pfad, den der PHP-Server unter `/_share/vendor/js/ace/` ausliefern muss.
- **Vendoring**: pragmatisch machbar, aber lauter als CM5.
- **API**:
  ```javascript
  var ed = ace.edit("editor");
  ed.session.setMode("ace/mode/php");
  ed.setValue(text, -1);
  var s = ed.getValue();
  ```

### Monaco (raus)
- **Stärken**: VS-Code-Komfort, IntelliSense, exzellente TS/JS.
- **Schwächen**: `min/vs/` ist **~13 MB** mit 109 Files; allein das gechunkte `editor.api-*.js` ist ~3.7 MB. AMD-Loader ist offiziell deprecated, ESM-Pfad braucht Bundler. Workers und WASM-Assets erschweren das Vendoring zusätzlich.
- **Verdict**: passt nicht zu „lokal, konservativ" aus `Value.spec`.

## Empfehlung

**CodeMirror 5 als Vendor-Drop-in.**

Konkreter Pfad:
- `app/_share/vendor/js/codemirror.min.js` — `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.js` (oder `.min.js`).
- `app/_share/vendor/css/codemirror.css` — `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/lib/codemirror.css`.
- `app/_share/vendor/js/codemirror-modes/<lang>.js` pro Sprache aus `https://cdn.jsdelivr.net/npm/codemirror@5.65.21/mode/<lang>/<lang>.js`.
- Jeweils mit Originalheader (MIT-Comment) wie bei `Parsedown.php`.
- Initialer Modes-Satz für M004: `php`, `yaml`, `markdown`, `javascript`, `python`, `shell`, `sql`, `json`, `xml`, `css`, `htmlmixed`, `go`, `rust`, `ruby`, `clike`.

Begründung:
1. Ein-File-Core + ein-File-pro-Mode passt **wörtlich** auf `pm/decisions/0002-php-infra.md` („externe Libs als einzelne Files unter `app/_share/vendor/`") und auf `0004-ux-policy.md` („eingecheckte Vendor-Files mit Originalheader").
2. MIT, kein Composer, kein npm, kein Bundler — keine Reibung mit der Policy.
3. 110+ Sprachen out-of-the-box, alle gängigen abgedeckt.
4. Globales `CodeMirror`-Objekt ist trivial in unseren BSD/snake_case-IIFE-Wrapper zu kapseln (z. B. `app/_share/js/editor.js` mit `(function () { ... })();`).
5. Tabs werden ohnehin selbst gebaut: pro offenem File eine `CodeMirror`-Instanz in einem versteckten Pane, ein dünner JS-Tab-State (an `tree_collapse.js` angelehnt). Multi-Doc-Support hat keine der vier Libs eingebaut.

Risiko: CM5 ist „legacy". Mildernd: 5.65.21 erschien Feb 2026, Bugfix-Cadence ist intakt, Surface ist klein und stabil. Wenn CM5 in 3-5 Jahren wirklich kippt, ist Ace ein klarer Migrations-Pfad mit ähnlicher Drop-in-Form.

## Hooks for us

- **IIFE-Wrapper** um `CodeMirror.fromTextArea` in `app/_share/js/editor.js` — passt zu `0004-ux-policy.md` (BSD, snake_case). Kein direkter Aufruf der globalen API aus Templates.
- **Mode-Lazy-Load** pro Tab: `<script src="/_share/vendor/js/codemirror-modes/<lang>.js">` nur einfügen, wenn ein File der Sprache geöffnet wird. Vermeidet Initial-Payload-Bloat.
- **Tabs als eigene UI-Schicht** (kein Vendoring-Versuch eines Tab-Plugins). Pro Tab eine eigene CM-Instanz, `editor.refresh()` beim Activate.
- **Save-Hook** über `editor.on("change", debounce(...))` → AJAX `POST /file.php` (folgt Content-Loader-Muster aus `app/_share/js/content_loader.js`).
- **Mode-Mapping**: `.php → text/x-php`, `.spec → yaml`, `.md → markdown`. Mini-Helper in `editor.js`, keine Server-Roundtrip nötig.
- **Decision-Pflicht** anschließend: `pm/decisions/0006-editor-library.md` mit Verweis hierher.

## Sources

- https://codemirror.net/5/ — CodeMirror 5 Homepage, 5.65.21, MIT, 110+ Sprachen.
- https://github.com/codemirror/codemirror5 — Repo, 27.2k Stars, Release 5.65.21 (2026-02-07).
- https://cdn.jsdelivr.net/npm/codemirror@5.65.21/ — Vendor-Quelle.
- https://codemirror.net/ — CodeMirror 6 Projektseite.
- https://codemirror.net/docs/changelog/ — CM6 Release-Cadence (May 2026).
- https://github.com/paul-norman/codemirror6-prebuilt — Community Pre-Built, 13 Stars, last push 2025-08-17.
- https://discuss.codemirror.net/t/codemirror-6-distribution-package/6705 — offizielle Empfehlung, dass CM6 ohne Bundler nicht direkt nutzbar ist.
- https://ace.c9.io/ — Ace Homepage.
- https://github.com/ajaxorg/ace — Ace Repo, 27.1k Stars, BSD.
- https://github.com/ajaxorg/ace-builds/tree/master/src-min-noconflict — Vendor-Bundle-Form.
- https://github.com/microsoft/monaco-editor — Monaco, MIT, v0.55.1 (2025-11-20), 46k Stars.
- https://app.unpkg.com/monaco-editor@0.55.1/files/min/vs — `min/vs/` ist ~13 MB, 109 Files.
- https://deepwiki.com/microsoft/monaco-editor/5.2-amd-integration-(deprecated) — AMD-Loader-Pfad ist deprecated.
