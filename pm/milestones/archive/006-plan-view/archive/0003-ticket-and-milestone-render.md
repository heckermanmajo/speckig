# 0003 — Markdown-Render im rechten Panel + AJAX-Loader

Blocked by: 0002

## Done when

- Neuer JSON-Endpoint `app/pm.php` (oder Wiederverwendung von `app/file.php` mit `pm/`-erlaubtem Pfad — entscheide selbst, dokumentier im Done):
  - Eingabe: `?path=pm/milestones/.../*.md` oder `?path=pm/bugs/.../*.md`.
  - Output: `{ok, path, html}` mit `html` = Parsedown-Render des Markdown-Inhalts.
  - Pfad-Traversal-Schutz wie in `file.php`. Pfad MUSS mit `pm/` beginnen (kein `app/`, kein Repo-Root).
- Neuer JS-Loader `app/_share/js/plan_loader.js` (oder Erweiterung von `content_loader.js` — pragmatisch entscheiden, aber **keine** Vermischung von Tree-Klick-Handlern und Plan-Klick-Handlern, die sich gegenseitig stoeren):
  - Klick auf einen Sidebar-Link in `plan.php` -> AJAX an den Endpoint -> rendert Markdown im `<article id="content">`.
  - URL-Sync via `history.pushState` (`/plan.php?path=...`).
  - Bookmark/Reload mit `?path=...` rendert die richtige Seite (initial-Load wie in `content_loader.js`).
  - Sidebar-Link wird visuell als aktiv markiert (CSS-Klasse `active`), wenn sein `?path` der aktuelle Pfad ist.
- Im rechten Panel oberhalb des Markdown wird ein kleiner Header gezeigt: bei Tickets `Status: open|done`, bei Milestones `Status: <status>`. Status wird aus dem Markdown extrahiert (Tickets haben kein eigenes `Status:`-Feld — leite aus dem Pfad ab: `open/` -> `open`, `archive/` -> `done`. Milestones haben `Status:`-Zeile.).
- Markdown-Render via vorhandenem `Parsedown` (`app/_share/vendor/Parsedown.php`).
- Smoketest gegen `php -S 127.0.0.1:8099 -t app`:
  - `curl -s "http://127.0.0.1:8099/pm.php?path=pm/milestones/005-spec-parser/milestone.md"` liefert JSON mit `html` enthaelt `<h1>` und Ticket-Liste.
  - `curl -s "http://127.0.0.1:8099/pm.php?path=app/index.php"` -> 400 (nicht unter `pm/`).
  - `curl -s "http://127.0.0.1:8099/pm.php?path=pm/../etc/passwd"` -> 400.
- `@spec`-Bloecke an neuen Funktionen / Endpoint.
- Box im milestone.md fuer 0003 abhaken. Wenn alle drei Tickets durch sind: `Status: done`, Milestone-Folder via `git mv pm/milestones/006-... pm/milestones/archive/006-...` im selben Commit.

## Verifikation

1. `php -l app/pm.php` (oder `file.php`, falls erweitert) sauber.
2. JS-Konsole im Browser zeigt keine Errors beim Klick auf einen Sidebar-Link.
3. Klick auf `005-spec-parser/milestone.md` -> rechts Markdown sichtbar, Ueberschrift, Ticket-Liste.
4. Klick auf ein archiviertes Ticket -> rechts Inhalt + Status-Header `done`.
5. Klick auf ein offenes Ticket -> Status-Header `open`.
6. Bookmark: `/plan.php?path=pm/milestones/005-spec-parser/milestone.md` direkt aufrufen -> Sidebar + rechts schon gerendert.
7. Server stoppen.

## Out of scope

- Editieren / Bearbeiten der Markdown-Files (read-only).
- Spezialisierter Ticket-Renderer (Done-when als interaktive Liste etc.).
- Commits-View — eigener spaeterer Milestone.

## Done

### Files

- `app/pm.php` (neu): JSON-Endpoint fuer den Plan-View AJAX-Loader.
  Eingabe `?path=pm/...md`, Antwort `{ok, path, html, status}`. Pfad-Schutz
  in fuenf Schichten: kein `..`, kein fuehrender `/`, MUSS mit `pm/` beginnen,
  Endung MUSS `.md` sein, realpath muss innerhalb des Repo-Roots liegen,
  is_file muss true sein. Bei Verstoss HTTP 400 + `{ok:false, message}`.
  Markdown-Render ueber `Parsedown`. Status-Ableitung: Tickets unter `/open/`
  -> `"open"`, unter `/archive/` -> `"done"`, `milestone.md` -> Wert der
  `Status:`-Zeile (zeilenweise via `explode("\n")` + `str_starts_with` —
  kein Regex), sonst `""`. `@spec`-Header oben.
- `app/_share/js/plan_loader.js` (neu): AJAX-Loader fuer die Plan-View.
  Bindet sich an `a.plan-milestone-link, a.plan-ticket-link`, fetcht
  `/pm.php`, rendert `<div class="plan-status-header">Status: <status></div>`
  + `<div class="plan-markdown">data.html</div>` ins `<article id="content">`.
  Status-Header via `textContent` (XSS-sicher), Markdown via `innerHTML`
  (Repo-kontrollierter Parsedown-Output, V1-Vertrauen). `history.pushState`
  + `popstate` + Bookmark/Reload-Initial-Load via `replaceState`. Sidebar-Link
  bekommt CSS-Klasse `active` beim Klick (vorherige aktive werden geleert).
  `@spec`-Header oben, BSD-Klammern, snake_case, async/await, defensive
  try/catch um fetch und json.
- `app/_share/css/app.css` (geaendert): zwei neue Regeln am Ende —
  `.plan-status-header` (grauer Header-Streifen oben im Render-Bereich)
  und `.plan-ticket-link.active, .plan-milestone-link.active` (gelber
  Hintergrund + bold).
- `app/plan.php` (geaendert): `<script src="/_share/js/plan_loader.js"></script>`
  nach `tree_collapse.js` eingebunden. Sonst keine Aenderung.

### Endpoint-Entscheidung: eigenes `pm.php` statt `file.php`-Erweiterung

Eigenes `app/pm.php`. Begruendung:

- `file.php` haengt am Spec-Parser (M005/0005), liefert ein vier-Feld-Schema
  `{ok, path, html, spec}` und unterstuetzt nicht-md-Dateien als `<pre>`.
  Plan-View braucht das nicht — nur Markdown, Status statt Spec.
- `file.php`-Pfad-Schutz erlaubt heute jeden Pfad innerhalb des
  Speckig-Roots; eine `pm/`-Restriktion einzubauen waere ein Cross-Cutting
  Concern, der die Tree-View auf `/file.php?path=app/index.php` brechen
  wuerde.
- Trennung der Endpoints macht Loescher-Tests trivial: pm.php weg, kein
  Plan-View. file.php weg, kein Tree-Render.
- Beide Endpoints nutzen denselben Parsedown — kein Code-Duplikat-Schmerz.

### Loader-Entscheidung: separater `plan_loader.js`

Separater `plan_loader.js` (Empfehlung des Tickets). Begruendung:

- Plan-View und Tree-View haben verschiedene Sidebar-Selektoren
  (`a.plan-*-link` vs. `nav a[href*="?path="]`). Ein gemeinsamer Loader
  haette eine Body-Klasse-Branch gebraucht, was die Klick-Handler-Bindung
  fragiler macht.
- `content_loader.js` rendert den Spec-Tab-Switch — fuer Markdown-only
  ist das unnoetiger Overhead.
- `plan_loader.js` laeuft NUR in `plan.php` (script-tag nur dort), ist
  in `index.php` nicht eingebunden. Umgekehrt: `content_loader.js`
  laeuft nicht in `plan.php`. Saubere Trennung, einzeln loeschbar.

### Bewusste V1-Entscheidung: Markdown-XSS-Hardening out of scope

`data.html` aus `pm.php` ist Parsedown-Output ueber Repo-kontrolliertem
Markdown (`pm/...md`-Files). Wir setzen es direkt via `innerHTML` in
den Markdown-Wrapper. Repo-Inhalt ist vertrauenswuerdig fuer V1; ein
echtes XSS-Hardening (HTML-Sanitizer wie HTMLPurifier oder DOMPurify)
ist explizit out of scope (Ticket-Hinweis). Status-Header dagegen
nutzt `textContent` und ist auch bei boesem `Status:`-Wert sicher.

### Status-Quelle bei `milestone.md`

Statt `pm_reader::list_milestones()` zu durchsuchen lese ich die
`Status:`-Zeile direkt im schon geladenen `$raw_file_contents`
zeilenweise per `explode("\n")` + `str_starts_with` (kein Regex,
konsistent mit Decision 0006 und mit dem `read_status_line` in
`pm_reader`). Das spart einen kompletten Tree-Walk pro Request und
arbeitet mit dem Inhalt, den wir ohnehin schon im Speicher haben.

### Verifikation

- `php -l app/pm.php` -> No syntax errors detected.
- `php -l app/plan.php` -> No syntax errors detected.
- `php -S 127.0.0.1:8099 -t app` gestartet (Bash background, NICHT 8083).
- `curl -s "/pm.php?path=pm/milestones/005-spec-parser/milestone.md"`:
  `ok=True`, `status='planned'`, html `<h1>005 — Spec-Parser ...`.
- `curl -s "/pm.php?path=pm/milestones/006-plan-view/archive/0001-pm-reader-and-shared-header.md"`:
  `ok=True`, `status='done'`, html `<h1>0001 — pm_reader ...`.
- `curl -s "/pm.php?path=pm/milestones/005-spec-parser/open/0003-js-parser.md"`:
  `ok=True`, `status='open'`, html `<h1>0003 — JS-Parser</h1>...`.
- `curl -s "/pm.php?path=app/index.php"`: HTTP 400, `{ok:false, message:"Ungueltiger Pfad."}`.
- `curl -s "/pm.php?path=pm/../etc/passwd"`: HTTP 400, `{ok:false, message:"Ungueltiger Pfad."}`.
- `curl -s "/pm.php?path=pm/decisions/0001-bootstrap.md"`: `ok=True`,
  `status=''` (kein open/archive/milestone), html enthaelt Decision-Inhalt.
  Erlaubt — Sidebar zeigt Decisions heute zwar nicht, aber pm.php muss
  nicht restriktiver sein als der Pfad-Vertrag.
- `curl -s "/pm.php?path=pm/somefile.txt"`: HTTP 400 (Endung nicht `.md`).
- `curl -s "/pm.php"`: HTTP 400 (kein path).
- `curl -s -o /dev/null -w "%{http_code}" "/plan.php?path=pm/milestones/005-spec-parser/milestone.md"`
  -> 200.
- `curl -s "/plan.php" | grep -o "plan_loader.js"` -> Treffer (Script eingebunden).
- `curl -s "/_share/css/app.css" | grep -o "plan-status-header\|plan-ticket-link.active"`
  -> beide Regeln vorhanden.
- Server via `TaskStop` beendet, Port 8083 nicht angefasst.
- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"`
  -> nur `./app.sqlite` (kanonisch).

### Edge-Cases / Notizen

- `pm.php` lehnt Pfade ohne `.md`-Endung schon vor dem realpath ab —
  spart einen Filesystem-Hit und macht den Vertrag explizit (V1
  rendert nur Markdown).
- `pm_reader::read_markdown()` macht eine zweite Schicht Pfad-Schutz;
  doppelte Validierung ist ok und faengt einen leeren Read defensiv ab
  (HTTP 400 statt unklarem `html=""`-Erfolg).
- Beim Klick auf einen Link geht der Status-Header verloren, falls
  `data.status` leer ist — gewollt, kein leerer "Status: " Streifen.
- Initial-Load mit `?path=` ueberschreibt den `<p>`-Platzhalter im
  Article — Verhalten konsistent mit `content_loader.js`.
- Beim popstate ohne `state.path` (z.B. zurueck zum Initial-Stand der
  Plan-View) wird der gespeicherte Initial-HTML-Block wiederhergestellt.
- `plan_loader.js` und `content_loader.js` koexistieren NICHT in
  derselben Seite: index.php bindet nur content_loader, plan.php nur
  plan_loader. Damit keine Doppel-Klick-Handler auf gleichen Anchors.
