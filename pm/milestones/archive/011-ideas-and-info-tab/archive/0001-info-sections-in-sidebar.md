# 0001 — Prototyp: Info-Sektionen (Ideas/Reports/Decisions/Audits/Terms) in der Plan-Sidebar

Prototyp fuer M011. Linke Sidebar in `plan.php` zeigt zusaetzlich zu
Milestones und Bugs einen "Info"-Abschnitt mit fuenf ausklappbaren
`<details>`-Sektionen — eine pro Inhaltstyp. Klicks rendern Markdown
rechts ueber den bereits existierenden `pm.php`-Endpoint; JS-Loader
muss nicht geaendert werden, weil die Selektor-Klasse
`plan-ticket-link` wiederverwendet wird.

## Done when
- `_share\pm_reader::list_info_sections(): array` liefert ein assoziatives
  Array mit den Schluesseln `ideas`, `reports`, `decisions`, `audits`,
  `terms`. Pro Schluessel: Liste von `{slug, path, title}` (gleiche Form
  wie bei `list_bugs()["open"]`), sortiert nach Slug.
- Spec direkt am Code (`@spec`-Block analog zu den bestehenden Methoden).
- `plan.php` rendert nach dem Bugs-Block eine `<h2>Info</h2>`-Sektion mit
  fuenf `<details data-path="pm/<name>">`-Bloecken. Bei leerer Sektion
  steht `<p class="plan-tickets-empty">keine</p>` drin (analog zu Bugs).
- Klick auf einen Info-Eintrag laedt die Datei via `/pm.php?path=...`,
  zeigt sie rechts als Markdown an, setzt Header-Label und URL-Pfad
  (vorhandenes `plan_loader.js` macht das automatisch durch
  Wiederverwendung von `a.plan-ticket-link`).
- `tree_collapse.js` persistiert den Open/Closed-Zustand der neuen
  `<details>` (passiert von alleine durch `data-path`).
- Pfad-Sicherheit: alle Strings durch `app::escape()`.

## Verifikation
- `php -l app/_share/pm_reader.php app/plan.php` — keine Syntax-Fehler.
- `php -S 127.0.0.1:8084 -t app` starten, dann:
  - `curl -s 'http://127.0.0.1:8084/plan.php' | grep -c 'data-path="pm/ideas"'` — gibt 1 zurueck.
  - `curl -s 'http://127.0.0.1:8084/plan.php' | grep -c 'data-path="pm/decisions"'` — gibt 1.
  - `curl -s 'http://127.0.0.1:8084/pm.php?path=pm/ideas/talk_about_code_base.md' | grep -c '"ok":true'` — gibt 1.
  - `curl -s 'http://127.0.0.1:8084/pm.php?path=pm/decisions/0001-spec-layer-landscape.md' | grep -c '"ok":true'` — gibt 1.
- Browser-Smoketest manuell durch User (kein automatisierter Browser-Test).

## Out of scope
- Inline-Edit / Neue Files anlegen (das ist M014).
- CSS-Polish jenseits der bestehenden Sidebar-Klassen.
- Filter / Suche.
- "Info"-Tab als separater Header-Tab — bleibt in der Plan-Sidebar.

## Done

### Files

- `app/_share/pm_reader.php` (geaendert): neue statische Methode
  `list_info_sections(): array`. Liefert assoziatives Array mit fixer
  Reihenfolge `ideas, reports, decisions, audits, terms`; pro Schluessel
  ein Array von `{slug, path, title}` — identisch zur Form von
  `list_bugs()["open"]`. Implementierung delegiert komplett an die
  bestehende private Helper `collect_tickets_in_dir()` (kein neuer
  Iterator, kein Regex). `@spec`-Block direkt drueber, Stil analog zu
  den anderen Methoden.
- `app/plan.php` (geaendert): neue Funktion `render_info_section(
  string $heading, array $entries, string $section_data_path): string`,
  strukturell sehr nah an `render_bugs_block` — bewusst dupliziert statt
  abstrahiert (Don't add abstractions beyond what the task requires,
  siehe `pm/how-to/code_style.md`). Klick-Targets nutzen
  `plan-ticket-link`, damit `plan_loader.js` ohne Aenderung greift.
  Sidebar bekommt nach dem Bugs-Block einen neuen
  `<h2>Info</h2>`-Abschnitt mit fuenf `<details class="plan-bugs"
  data-path="pm/<name>">`-Bloecken in der Reihenfolge Ideas, Reports,
  Decisions, Audits, Terms.
- Keine Aenderung an `plan_loader.js` (Klassen-Wiederverwendung) und
  keine Aenderung an `app/_share/css/app.css` (existierende Klassen
  `plan-bugs`, `plan-section-heading`, `plan-tickets`, `plan-ticket-link`,
  `plan-tickets-empty` reichen).

### Verifikation

- `php -l app/_share/pm_reader.php` -> No syntax errors detected.
- `php -l app/plan.php` -> No syntax errors detected.
- `php -S 127.0.0.1:8084 -t app` gestartet (Bash background, NICHT 8083).
- `curl -s '/plan.php' | grep -c 'data-path="pm/ideas"'` -> 1.
- `curl -s '/plan.php' | grep -c 'data-path="pm/reports"'` -> 1.
- `curl -s '/plan.php' | grep -c 'data-path="pm/decisions"'` -> 1.
- `curl -s '/plan.php' | grep -c 'data-path="pm/audits"'` -> 1.
- `curl -s '/plan.php' | grep -c 'data-path="pm/terms"'` -> 1.
- `curl -s '/plan.php' | grep -c '>Info<'` -> 1.
- `curl -s '/plan.php' | grep -c 'talk_about_code_base'` -> 1.
- `curl -s '/plan.php' | grep -c '0001-bootstrap'` -> 1.
- `curl -s '/plan.php' | grep -c 'keine</p>'` -> 1 (terms ist leer).
- `curl -s '/pm.php?path=pm/ideas/talk_about_code_base.md' | grep -c '"ok":true'` -> 1.
- `curl -s '/pm.php?path=pm/decisions/0001-bootstrap.md' | grep -c '"ok":true'` -> 1.
- Server via TaskStop beendet, Port 8083 nicht angefasst.
- `find . -name "app.sqlite*" -not -path "./.git/*"` -> nur `./app.sqlite`.

### Notizen

- `pm.php` akzeptiert jeden validen `pm/<...>.md`-Pfad — Decisions,
  Reports, Audits, Ideas und Terms brauchen keinerlei Endpoint-Aenderung.
- `pm/terms/` ist heute leer und zeigt den Empty-State `keine` — gewollte
  Konsistenz, wie im Ticket-Direktiv beschrieben.
- Code-Duplikation zwischen `render_info_section` und `render_bugs_block`
  ist gewollt: gemeinsame Abstraktion wuerde die Klick-Selektor-Klassen
  und das `data-path`-Schema kuenstlich verschmelzen; im Zweifel neue
  Funktion, kleines Diff.
