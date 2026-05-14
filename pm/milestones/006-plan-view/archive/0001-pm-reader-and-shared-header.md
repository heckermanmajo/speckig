# 0001 — pm_reader + geteilter Header

Blocks: 0002, 0003

## Done when

- Neue Datei `app/_share/pm_reader.php` mit statischem Funktions-Buendel `pm_reader` (lowercase nach Decision 0003).
- `pm_reader::list_milestones(): array` liefert ein Array mit zwei Schluesseln `active` und `archived`. Jeder Eintrag ist ein Array mit:
  - `slug` (z.B. `005-spec-parser`)
  - `path` (relativ zum Repo-Root, z.B. `pm/milestones/005-spec-parser`)
  - `title` (aus erster `# NNN — <Title>`-Zeile von `milestone.md`)
  - `status` (aus `Status:`-Zeile in `milestone.md`, z.B. `planned`/`active`/`done`/`dropped`; leer wenn nicht gefunden)
  - `tickets_open[]` und `tickets_archive[]`: jeweils Array von `{slug, path, title}` (Title aus erster `# NNNN — <Title>`-Zeile des Ticket-md).
- `pm_reader::list_bugs(): array` analog mit Schluesseln `open` und `archive`, je `{slug, path, title}`.
- `pm_reader::read_markdown(string $repo_relative_path): string` liest eine Datei unter `pm/` und gibt den Inhalt zurueck. Pfad-Traversal wird abgewiesen (kein `..`, kein fuehrender `/`, Pfad muss mit `pm/` beginnen).
- Pfad-Listing nutzt `scandir`, kein Regex auf Filenamen — Filenamen werden mit `pathinfo`/`str_starts_with` geprueft.
- Keine SQL, keine externen Libs.
- Header-Markup wird aus `app/index.php` ausgezogen in eine kleine helper-Funktion oder Snippet, die sowohl `index.php` (Tree-View) als auch eine spaetere `plan.php` (Plan-View) nutzen koennen. Konkrete Form: entweder eine Funktion in `app/_share/app.php` (oder einem neuen `app/_share/html/header.php`) oder ein include-Snippet unter `app/_share/html/`. Entscheide pragmatisch und dokumentier im Done.
- Header bekommt zwei Links links: `Files` (-> `/index.php`) und `Plan` (-> `/plan.php`). Aktive View ist via `aria-current="page"` und/oder eine `active`-Klasse markiert. CSS-Regel(n) fuer den View-Switch ergaenzen.
- Bestehender Header-Inhalt (Repo-Label, Pfad-Label) bleibt unveraendert sichtbar in der Tree-View. In der Plan-View darf das Pfad-Label leer/anders sein — entscheide pragmatisch (z.B. zeigen den Pfad der gerade angeklickten Ticket/Milestone-Datei).
- `@spec`-Bloecke an den neuen Funktionen in `pm_reader` und ggf. an `header.php`.

## Verifikation

1. `php -l app/_share/pm_reader.php` sauber.
2. `php -r 'require "app/_share/pm_reader.php"; var_export(\\_share\\pm_reader::list_milestones());'` zeigt `005-spec-parser` (active, weil noch nicht in `archive/`) mit den Tickets `0003`, `0004` (open) und `0001`, `0002`, `0005` (archive). Archived: `001-...`, `002-...`, `003-...`, `004-...`.
3. `php -r '... list_bugs() ...'` — Bugs-Liste leer oder gefuellt, je nach Repo-Stand. Form pruefen.
4. `php -r '... read_markdown("pm/milestones/005-spec-parser/milestone.md") ...'` liefert den File-Content, lehnt `..` und `/etc/passwd` ab.
5. Server starten (`php -S 127.0.0.1:8099 -t app`), `/index.php` zeigt die zwei neuen Header-Links, `Files` ist als aktiv markiert. `Plan`-Link ist klickbar, fuehrt heute auf 404 (das ist OK — `plan.php` kommt in 0002).
6. Server stoppen.

## Out of scope (in diesem Ticket)

- `plan.php` selbst — das ist 0002.
- Sidebar-Markup fuer Milestones/Bugs — das ist 0002.
- Markdown-Render im rechten Panel — das ist 0003.

## Done

### Files

- `app/_share/pm_reader.php` (neu): statisches Funktions-Buendel `_share\pm_reader`
  mit `list_milestones()`, `list_bugs()`, `read_markdown()`. Read-only,
  scandir-basiert, keine Regex auf Pfaden/Filenamen, kein SQL. `@spec`-Bloecke
  an Datei, Klasse und allen Funktionen (auch private Helpers).
- `app/_share/html/header.php` (neu): `_share\html\header::render($active_view, $path_label, $repo_label)`.
  Liefert HTML-String fuer den `<header>`-Block, escapt `$path_label`/`$repo_label`
  intern. Lowercase-Klassenname (Decision 0003).
- `app/index.php`:
  - `use _share\html\header;` ergaenzt.
  - `<header>`-Block durch `<?= header::render("files", $header_path_label, $header_root_label) ?>` ersetzt.
  - CSS-Regeln fuer `.header-nav`, `.header-nav-link`, `.header-nav-link.active`/`[aria-current="page"]`
    in den `<style>`-Block ergaenzt — fettes Label + 2px-Underline auf der aktiven View.

### Design-Entscheidung: Funktion vs. Snippet

Funktion in `app/_share/html/header.php` mit Rueckgabe-String, kein
Echo-Snippet. Gruende:
- Ist konsistent zur statischen Funktions-Buendel-Konvention (`app`, `db`,
  `document`, `cards`).
- Caller bestimmt selbst, wann/wo der Output landet — wichtig z.B. fuer
  `plan.php` (M006/0002), wenn das Pfad-Label dort spaeter dynamisch
  aus dem geladenen Dokument kommt.
- Test/Reuse: String-Rueckgabe laesst sich in Snapshot-Tests pruefen, ohne
  Output-Buffering-Tricks.
- XSS: Aufrufer muss nicht escapen, Funktion macht das selbst via
  `app::escape()`. `$active_view` selbst landet nie im Output (nur als
  Branch fuer Klasse/aria), daher kein Escape noetig.

### Verifikation

- `php -l app/_share/pm_reader.php` -> No syntax errors detected.
- `php -l app/_share/html/header.php` -> No syntax errors detected.
- `php -l app/index.php` -> No syntax errors detected.
- `php -r 'require "app/_share/pm_reader.php"; var_export(\\_share\\pm_reader::list_milestones());'` (gekuerzt):
  - active[0] = `005-spec-parser`, status `planned`, tickets_open `0003-js-parser` + `0004-parser-fixture-tests`,
    tickets_archive `0001-parser-architecture-and-schema` + `0002-php-parser` + `0005-ui-spec-view-in-file-render`.
  - active[1] = `006-plan-view`, status `planned`, tickets_open `0001-...` + `0002-...` + `0003-...`.
  - archived = `001-php-infra-bootstrap`, `002-ux-ui-work`, `003-specs-for-existing-code`,
    `004-spec-as-comment-pilot`, jeweils status `done`.
- `php -r 'require "app/_share/pm_reader.php"; var_export(\\_share\\pm_reader::list_bugs());'`:
  - `open` -> [] (Ordner leer, korrekte Form).
  - `archive` -> `0001-parsedown-php85-deprecations`, `0002-toplevel-files-no-linebreak`.
- `read_markdown("pm/milestones/005-spec-parser/milestone.md")` -> 1215 Bytes Inhalt,
  beginnt mit `# 005 — Spec-Parser ...`.
- `read_markdown("../etc/passwd")`, `"/etc/passwd"`, `"app/index.php"`,
  `"pm/../app/index.php"`, `""` -> jeweils leerer String (Traversal/Wrong-Prefix
  blockiert).
- `php -S 127.0.0.1:8099 -t app` gestartet, `curl -s -o /dev/null -w "%{http_code}" /index.php`
  -> 200. `grep -c "header-nav-link"` -> 4 (zwei Links + zwei CSS-Regel-Selektoren).
  Active-Link: `<a href="/index.php" class="header-nav-link active" aria-current="page">`.
  Plan-Link: `<a href="/plan.php" class="header-nav-link">`.
  `/plan.php` antwortet aktuell mit 200, weil `php -S` ohne `-rrouter` bei nicht
  existierenden Files auf `index.php` zurueckfaellt (built-in Server-Verhalten).
  Sobald `app/plan.php` in M006/0002 angelegt ist, liefert das Subscript dort.
  Im Browser mit Apache/nginx waere es ein 404 — das Ticket erlaubt 404 explizit.
- Server via `TaskStop` beendet, Port 8083 nicht angefasst.
- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"` -> nur
  `./app.sqlite` (kanonisch).

### Edge-Cases / Notizen

- `read_first_title_line` und `read_status_line` lesen via `file()` und prueft
  zeilenweise per `str_starts_with` — kein Regex, konsistent zu Decision 0006.
- Repo-Root wird via `realpath(__DIR__ . "/../..")` aus `app/_share/` resolved
  — robust gegen CWD des Aufrufers (CLI mit `php -r` oder Webserver mit
  DocumentRoot=`app/`).
- Versteckte Eintraege (`.git`, `.foo`) werden in beiden Listen-Helpers
  ueberspringt; nicht-md-Dateien (z.B. ein versehentliches `.txt`) ignoriert.
- Aktiver Milestone-Loop ueberspringt den Eintrag namens `archive` per Flag,
  damit `pm/milestones/archive/` nicht als "Milestone" auftaucht. Bei
  `pm/milestones/archive/` selbst ist das Flag `false`, also kein Skip.
