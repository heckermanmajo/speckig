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
