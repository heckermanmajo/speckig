# 0005 — Content via AJAX + History

Klicks auf Tree-Links laden den Content per fetch in die rechte Spalte. URL spiegelt die aktuelle Datei via `history.pushState`. Browser-Back/Forward funktionieren.

See: pm/decisions/0004-ux-policy.md
Blocked by: 0003, 0004

## Done when
- Neue Datei `app/_share/js/content_loader.js` macht:
  - Click-Handler auf Tree-Links: `event.preventDefault()`, fetch `/file.php?path=…`, response.html in `<article>` injizieren.
  - Header rechts/oben zeigt den aktuellen Pfad nach dem Load.
  - `history.pushState({path}, "", "/?path=...")` bei jedem erfolgreichen Load.
  - `popstate`-Handler: lädt entsprechend nach Back/Forward.
- `app/index.php` lädt `content_loader.js` nach `helpers.js`.
- Bei initial-Page-Load mit `?path=…` in der URL: JS macht einen Fetch und füllt den Content.
- Ohne `?path` und ohne Klick: rechts steht `Datei links auswählen.` (wie bisher).
- Smoketest: im Browser auf drei verschiedene Files klicken → URL ändert sich, Content erscheint sofort, kein Full-Reload (Network-Tab zeigt nur file.php-XHRs). Back-Button geht durch die Historie.

## Done
- Neu: `app/_share/js/content_loader.js` — IIFE-gewickelter UX-Layer, BSD-Klammern, snake_case, `let what_cond_means`-Pattern, async/await mit defensivem `try/catch` um `fetch` und `response.json()`. Keine Globals geleakt.
- `app/index.php`:
  - `<article id="content">` damit JS das Content-Element findet.
  - `<span id="header-path-label">` um das `app::escape($header_path_label)` damit JS das Label nach Klick aktualisieren kann (Server-side-Initial-Render bleibt unverändert; bei leerem `$header_path_label` ist die Span leer — kein zusaetzlicher Em-Dash mehr conditional, der Em-Dash steht jetzt immer, was harmlos ist und das Markup deterministisch macht).
  - `<script src="/_share/js/content_loader.js"></script>` als drittes Script am Body-Ende, nach `tree_collapse.js`.
- JS-Logik:
  - `load_path(path, do_push_state)`: fetch `/file.php?path=<encoded>`; bei HTTP-400/`ok:false`/Netzwerkfehler → `<article>` auf `<p>Ungültiger Pfad.</p>`, Header-Label und Title geleert, `console.warn`. Bei Erfolg → `article_element.innerHTML = data.html`, Header-Label und `document.title` aktualisiert, und (wenn gewünscht) `history.pushState({path}, "", "/?path=" + encodeURIComponent(path))`.
  - Tree-Link-Handler auf `nav a[href*="?path="]`: `event.preventDefault()`, Pfad aus `new URL(link.href).searchParams.get("path")`, dann `load_path(path, true)`. Linke Maustaste only — Mittelklick-In-Neuem-Tab funktioniert weiter, weil `<a href="…">` als Fallback steht.
  - `DOMContentLoaded`: initiales `<article>`-`innerHTML` wird in `initial_article_html` festgehalten (für den popstate-no-state-Fall). Wenn die URL bereits `?path=…` enthält: `history.replaceState({path}, …)` setzt den state für sauberen Back-Button-Verlauf, dann `load_path(path, false)`. Der Server-side-Render hat den Content bereits eingesetzt; der zusätzliche Fetch ist idempotent und stellt Konsistenz zwischen URL, Header-Label und JS-Sicht her — wird in 0006 obsolet.
  - `popstate`: `event.state?.path` vorhanden → `load_path(state.path, false)` (kein erneutes pushState). Kein `state.path` → `<article>` zurueck auf `initial_article_html`, Header-Label und Title leer.
  - `document.title` wird bei Erfolg auf `<path> — speckig` gesetzt, sonst zurück auf `speckig`.
- Verifikation automatisch:
  - `php -l app/index.php` → `No syntax errors detected`.
  - `node --check app/_share/js/content_loader.js` → ok.
  - **Test A** (`curl -sI http://127.0.0.1:8080/_share/js/content_loader.js`) → `HTTP/1.1 200 OK`, `Content-Type: application/javascript`.
  - **Test B** (`grep -oE 'src="/_share/js/[a-z_]+\.js"'`) → Reihenfolge `helpers.js` → `tree_collapse.js` → `content_loader.js`.
  - **Test C** (`grep -oE '(id="content"|id="header-path-label")'`) → beide IDs im HTML.
  - Bonus: `/file.php?path=pm/decisions/0004-ux-policy.md` → `{"ok":true,…}`; `?path=../README.md` → `HTTP/1.1 400`.
- Manuell im Browser zu prüfen (kein Headless-Browser im Repo):
  - **Klick auf drei verschiedene Files im Tree**: URL wechselt zu `/?path=…`, `<article>`-Content tauscht aus, Header-Label zeigt den Pfad, **kein Full-Page-Reload** (Network-Tab zeigt nur `file.php`-XHRs, nicht den Root-Request).
  - **Back-Button** geht durch die Historie zurück: jeweils das vorherige File-HTML wird wieder eingesetzt (via `popstate` → `load_path(state.path, false)`).
  - **Back über den initialen Stand hinaus** (auf `/` ohne `?path`): `<article>` zeigt wieder `Datei links auswählen.`, Header-Label und Title sind leer.
  - **Reload mit `?path=…`-URL**: Initial-Fetch füllt Header-Label und Title, History-State ist via `replaceState` mit `{path}` belegt, Back-Button funktioniert konsistent.
- Code-Style: BSD-Klammern (Funktionen, `if`, `forEach`-Callbacks, `try/catch`), snake_case (`article_element`, `header_label_element`, `extracted_path`, `state_has_path`, `do_push_state`, `initial_path_is_set`), Condition-naming-Pattern (`article_exists`, `path_is_present`, `response_signals_error`, `state_has_path`), `let`/`const`, kein `var`, kein `import`/`export`. IIFE wrap analog `tree_collapse.js`.
- Ungewöhnliches: keiner — `<article>`-`innerHTML`-Setzen ist explizit Zweck des `file.php`-Endpoints (lokale, vertrauenswürdige `.md` über Parsedown; Non-MD über `<pre>` + `app::escape`). `encodeURIComponent` deckt Pfade mit Spaces/Sonderzeichen ab. `popstate`-without-state via gespeichertem `initial_article_html` sauber behandelt.
- Cleanup: 8080-Subagent-Server via `TaskStop` beendet; 8083 (User-Session) unangetastet. `find … app.sqlite*` zeigt nur den kanonischen `/home/mo/Schreibtisch/speckig/app.sqlite`.
