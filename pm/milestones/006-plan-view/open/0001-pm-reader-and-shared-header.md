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
