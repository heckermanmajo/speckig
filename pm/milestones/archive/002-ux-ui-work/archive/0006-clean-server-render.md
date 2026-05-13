# 0006 — Server-Render aufräumen

Nach 0005 macht `index.php` den Content-Render nicht mehr — JS übernimmt. Aufräumen, kein toter Code.

See: pm/decisions/0004-ux-policy.md
Blocked by: 0005

## Done when
- `app/index.php` rendert rechts initial nur den `Datei links auswählen.`-Hinweis.
- Die Markdown-Render-Logik (`Parsedown`-Aufruf, `<pre>`-Branch) wandert komplett nach `app/file.php` (war schon in 0004 dorthin gewachsen) — `index.php` lädt Parsedown nicht mehr.
- `index.php` ist deutlich kleiner. `wc -l app/index.php` zeigt eine Reduktion (verglichen mit dem Stand vor M002).
- Smoketest: `curl -s http://127.0.0.1:8080/?path=pm/decisions/0002-php-infra.md` enthält **nicht** das gerenderte Markdown. Es enthält den leeren Article + den Hinweis (JS würde es dann nachladen, aber curl führt kein JS aus).
- `php -l` auf beide Files clean.

## Done
- Raus aus `app/index.php`:
  - `require_once .../Parsedown.php` (Parsedown wird hier nicht mehr instanziiert).
  - Block `# --- Datei-Content rendern ---` komplett: `$rendered_content_html`-Aufbau, `.md`-Parsedown-Aufruf, `<pre>`-Plaintext-Branch, "Ungültiger Pfad."-Branch.
  - `<?= $rendered_content_html ?>` im `<article id="content">`; ersetzt durch statisches `<p>Datei links auswählen.</p>`.
- Geblieben (und warum):
  - `$speckig_root_abs`-Resolve: weiterhin nötig für `render_tree()` und `$header_root_label`.
  - Komplette `?path`-Validation (`$path_string_is_safe`, `$resolved_path_abs`, `$path_is_inside_root`, `$path_points_to_file`, `$path_is_valid`) **und** der `error_log()`-Reject-Log: brauchen wir noch für `$header_path_label`. Ohne diese serverseitige Validation würde der Header beim initialen Page-Load mit `?path=…` (Bookmark/Reload) einen kurzen Moment leer stehen, bevor `content_loader.js` ihn nachfüllt — sichtbares Flackern. Validation ist billig (drei `realpath`-Checks), das ist es wert. Damit bleibt auch die XSS-Härtung erhalten: das Label zeigt nur `$raw_path`, **wenn** validiert.
  - `render_tree()`, `count_visible_children()`, Header-Markup, Style-Block, Script-Tags: unangetastet.
- `wc -l app/index.php`: **vorher 252 → nachher 234** (Δ −18 Zeilen). Etwas weniger Delta als gefühlt, weil der erweiterte Kommentarblock (Erklärung warum Validation bleibt) ~10 Zeilen wieder rauffrisst — der reine Code-Delta ist grösser, ich habe lieber Doku statt Magie hinterlassen.
- `app/file.php` und `app/_share/js/content_loader.js`: nicht angefasst (Done-when erfüllt der Cleanup alleine).
- Verifikation automatisch (gegen `php -S 127.0.0.1:8080 -t app`):
  - `php -l app/index.php` → `No syntax errors detected`.
  - **Test A** — Initial-Render leer: `curl -s "/?path=pm/decisions/0002-php-infra.md" | grep -c "PHP-Mindestversion"` = `0`. Im Body steht `<article id="content"><p>Datei links auswählen.</p></article>`. Kein gerendertes Markdown serverseitig.
  - **Test B** — Tree und Header rendern: `grep -oE '(data-path="pm"|id="header-path-label")'` zeigt beide. Header-Label bekommt weiterhin den validierten `?path`-Wert (siehe oben).
  - **Test C** — `file.php` läuft weiter: `curl -s "/file.php?path=pm/decisions/0002-php-infra.md"` → `ok: True, html-has-h1: True`.
  - **Test D** — `grep -E "Parsedown|require_once" app/index.php` → no match (exit 1). Sauber.
- Manuell zu prüfen (kein Headless-Browser im Repo): Reload mit `?path=…` in der URL → AJAX-Loader (`content_loader.js` → `replaceState` + `load_path(initial_path, false)`) füllt den `<article>`-Content, Header-Label und `document.title` werden konsistent gesetzt, Network-Tab zeigt einen einzigen `file.php`-XHR. Click auf andere Tree-Files lädt ohne Full-Reload weiter.
- Scope-Disziplin: Validation-Konsolidierung (gemeinsamer Helper `app::resolve_path()` für `index.php` ↔ `file.php`-Duplikation) **nicht** angefasst — explizit out-of-scope laut Ticket-Briefing, der Hinweis in 0004 `## Done` reicht als Reminder. Wäre ein eigenes Ticket wert, sobald M003 das anpackt.
- Cleanup: 8080-Subagent-Server via `TaskStop` beendet; `ps aux | grep "php -S"` zeigt nur noch den User-Server 8083 (unangetastet). `find … app.sqlite*` zeigt nur den kanonischen `/home/mo/Schreibtisch/speckig/app.sqlite`.
- M002 abgeschlossen: dieses Ticket war die letzte Box. `milestone.md` Status `active` → `done`, Folder-Move `pm/milestones/002-ux-ui-work` → `pm/milestones/archive/002-ux-ui-work` im selben Commit.
