# 0006 — Speckig-Layout: Header + Tree links + Content rechts

See: pm/decisions/0002-php-infra.md
Blocked by: 0005

Ersetzt die vorherigen 0006-tree-view und 0007-md-render. Layout-Skizze:

```
+----------------------------------------------------+
| <header> speckig — aktueller Pfad </header>        |
+--------------------------+-------------------------+
| pm/ (Tree, alle offen)   | Datei-Inhalt            |
|   decisions/             |                         |
|     0001-bootstrap.md    | (Markdown gerendert     |
|     0002-php-infra.md    |  oder <pre>-Plaintext   |
|   how-to/                |  für nicht-.md)         |
|     …                    |                         |
+--------------------------+-------------------------+
```

Navigation: Klick auf Tree-Link → Full Page Reload mit `?path=pm/foo/bar.md`. Kein JS, kein Iframe. Alle Tree-Ordner werden vom Server immer `<details open>` gerendert.

## Done when
- `app/index.php` rendert auf `/` ein zwei-spaltiges Layout mit Header.
- Header zeigt `speckig` und den aktuellen `?path=…` (leer wenn kein Pfad).
- Linke Spalte (~50%): rekursiver Tree von `pm/` als HTML5 `<details open><summary>…</summary>…</details>`-Verschachtelung. Dateien als `<a href="/?path=…">…</a>`.
- Rechte Spalte (~50%): Inhalt der Datei aus `?path`. `.md` wird via vendored `app/_share/vendor/Parsedown.php` als HTML gerendert; andere Endungen als `<pre>`-Plaintext.
- `app/_share/vendor/Parsedown.php` ist eingecheckt mit Originalheader (Lizenz, Version).
- Pfad-Traversal (`..`, absolute Pfade, Pfade ausserhalb `pm/`) wird hart abgewiesen — Response zeigt einen kurzen Hinweis, kein File.
- Ohne `?path` rechts ein neutraler Hinweis: „Datei links auswählen".
- Layout via wenigen Zeilen `<style>` inline; kein eigenes CSS-File. Verwende CSS-Grid oder Flexbox für die 50/50-Spaltung.
- Semantische HTML5-Elemente: `<header>`, `<main>`, `<nav>` (für Tree) oder `<aside>` (Geschmack), `<article>` (für Content).
- `php -S 127.0.0.1:8080 -t app` antwortet 200 auf `/` und auf `/?path=pm/decisions/0002-php-infra.md`. Body enthält links den Tree, rechts gerendertes Markdown.

## Out of scope
- Editieren im Browser.
- Suche.
- Live-Reload.
- Tree-State persistieren (alle Ordner immer offen).
- Mobile-Variante.
- Auth.

## Done
- `app/_share/vendor/Parsedown.php` neu eingecheckt: Version `1.7.4`, MIT, gezogen von `https://raw.githubusercontent.com/erusev/parsedown/1.7.4/Parsedown.php`. Unverändert, Originalheader (Autor, Lizenz, `const version = '1.7.4'`) erhalten.
- `app/index.php` ersetzt: zwei-spaltiges Grid (`<header>` + `<main>` mit `<nav>` und `<article>`), Tree rekursiv via `render_tree()` als verschachtelte `<details open>`, Sortierung Ordner-vor-Dateien alphabetisch, Hidden-Files (Punkt-Prefix) gefiltert. Dateilinks als `?path=pm/…`. `.md` über `new Parsedown()->text()`, sonst `<pre>` mit `app::escape`.
- Pfad-Traversal-Schutz dreistufig: (1) String-Check (`pm/`-Prefix, kein `..`, kein führender `/`), (2) Realpath muss mit `$pm_root_abs . DIRECTORY_SEPARATOR` starten, (3) `is_file()`. Verstoss → `app::error_log(...)` + `<p>Ungültiger Pfad.</p>`, kein die.
- Header zeigt nur den validierten `$raw_path` an, nie ungeprüften Input (Reflected-XSS-Vermeidung).
- `app::escape()` wird im `render_tree()` über voll-qualifizierten Namen `\_share\app::escape` aufgerufen, weil die Funktion ausserhalb der Datei-`use`-Klausel scope-frei steht; im Top-Level-Code mit `use _share\app` direkt als `app::escape`.
- Inline `<style>`, kein eigenes CSS-File, kein JS. Grid mit `1fr 1fr`, Höhe `calc(100vh - 3rem)`, `overflow:auto` pro Spalte.
- Verifikation: `php -l app/index.php` und `php -l app/_share/vendor/Parsedown.php` → "No syntax errors". Smoketest gegen `php -S 127.0.0.1:8080 -t app`:
  - `GET /` → 200, Body enthält `<strong>speckig</strong>`, vollständigen Tree, `<article><p>Datei links auswählen.</p>`.
  - `GET /?path=pm/decisions/0002-php-infra.md` → 200, `<article><h1>0002 — PHP infra</h1>` + `<li>`-Bullets gerendert.
  - `GET /?path=../README.md`, `?path=pm/../README.md`, `?path=/etc/passwd`, `?path=pm/decisions/nonexistent.md` → jeweils `<article><p>Ungültiger Pfad.</p>`, kein Leak.
- Streu-File-Check: `find … -name "app.sqlite*"` zeigt nur den kanonischen Pfad. Server nach Tests gestoppt.
- README unangetastet — der `## Run`-Abschnitt aus 0005 deckt das ab.
