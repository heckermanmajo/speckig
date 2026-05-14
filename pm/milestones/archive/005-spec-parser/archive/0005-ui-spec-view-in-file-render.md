# 0005 — UI: Parser-Output ueber Datei-Inhalt anzeigen

See: pm/ideas/spec-as-comment.md
Blocked by: 0002, 0003

## Done when

- `app/file.php` ruft fuer `.php`- und `.js`-Dateien den Parser aus M005/0001 auf, **bevor** der Code-Inhalt ausgeliefert wird.
- Antwort an den Browser enthaelt zusaetzlich zum heutigen `html`-Feld ein `spec`-Feld (`null` falls Datei keine Spec hat oder Sprache nicht unterstuetzt wird).
- `spec`-Feld ist das Parser-JSON aus 0001/0002/0003.
- Im Frontend wird der Spec-Block ueber dem Code als eigene Sektion gerendert:
  - Datei-Spec ganz oben.
  - Pro Symbol: Signatur (aus dem Parser-Output) + Spec-Zeilen darunter.
  - Cursor `pointer` und visuelles Styling konsistent mit bestehender Tree-/Card-Optik.
  - Spec-View ist kollabierbar (Default: aufgeklappt). Status muss nicht persistiert werden.
- Bestehende `.spec`-Datei-Anzeige (aktuelle Plaintext-Render von `.spec` via `file.php`) bleibt unangetastet — Dateien, die noch nicht migriert sind, werden weiter aus der `.spec`-Datei daneben gelesen. Das Spec-View aus dem Parser greift nur, wenn die `.php`/`.js`-Datei tatsaechlich `// @spec`-Bloecke enthaelt.
- Smoketest: `php -S 127.0.0.1:8099 -t app` starten (DocumentRoot ist `app/`, nicht Repo-Root — nicht Port 8083 nutzen, der gehoert dem User), `curl "http://127.0.0.1:8099/file.php?path=app/user/data/User.php"` aufrufen, Spec-View zeigt Datei-Spec + sechs Felder mit Specs. `?path=app/user/actions/CreateUserAction.php` zeigt Klassen-Spec + Methode `execute` mit Intent + Conditions. Server am Ende beenden.
- `php -l` sauber, JS ohne Console-Errors im Browser.

## Aus der Idea wichtig

- Spec-View ersetzt die `.spec`-Datei NICHT generell — sie ist die Anzeige fuer migrierte Dateien. Migration ist ein eigener Milestone (M006).
- Spec-View darf KEINE Typen aus dem Spec-Text rendern, die schon in der Signatur stehen — sonst doppelt. Renderer nimmt Typen aus Parser-Output.
- Performance: Re-Parsen pro Request ist OK fuer den Pilot. Caching ist Out-of-scope (eigene offene Frage in der Idea).

## Done

- `app/file.php` (+89 Zeilen): require_once auf `_share/spec_parser/spec_parser.php`,
  `use _share\spec_parser\spec_parser`. Vor dem `exit(json_encode(...))`-Block
  wird fuer `.php`/`.js`-Endungen `spec_parser::parse($resolved_path_abs)`
  aufgerufen. Absoluter Pfad rein, weil der Parser intern `is_file($path)` ohne
  CWD-Annahme nutzt; Vendor-Substring-Check matcht trotzdem (Substring
  `app/_share/vendor/` steht auch im absoluten Pfad). Filter-Logik:
  - Error-Schema (`vendor`, `unsupported language`) -> `spec` = null (kein
    Mehrwert im UI; das Ticket erlaubt durchreichen oder null, wir nehmen null).
  - Schema ohne irgendeine spec-Zeile (kein `file_spec`, und rekursiv kein
    Symbol/Member mit `spec[]`-Eintrag) -> `spec` = null. Faengt z.B. db.php
    ab, wo der Parser zwar Symbole findet, aber keine `@spec`-Bloecke
    existieren. Implementiert als selbst-referenzielle Closure `$spec_walker`,
    damit kein Top-Level-Helper im Endpoint-Skript haengt.
  - Sonst: volles Schema-Array unter `"spec"`.
- `app/_share/js/content_loader.js` (+~280 Zeilen): neue Render-Schicht oberhalb
  von `load_path()`:
  - `escape_html()` via `document.createElement("div").textContent = …;
    return div.innerHTML;`. XSS-sicher, deckt alle Quotes/Tags ab — keine
    eigene Stringkonstruktion mit innerHTML auf User-/Spec-Inhalten.
  - `render_signature_for_symbol(symbol)`: dispatcht nach `kind`. Methods/
    Funktionen nehmen `symbol.signature` direkt (Schema garantiert das).
    Properties synthetisieren `public <type> $<name> = <default>` (Parser
    liefert nur `name`/`type`/`default`, keinen Source-Modifier — `public`
    als pragmatischer Default; Property-Visibility ist im aktuellen
    PHP-Parser-Output nicht enthalten und out of scope hier). Const analog
    `const [<type> ]<NAME>[ = <default>]`. class/interface/trait als
    `<kind> <name>[ extends ...][ implements ...]`. local-spec als
    `// <name>`-Marker.
  - `render_symbol(symbol)` ist rekursiv: rendert Signatur als `<code>`,
    spec-Zeilen als `<p>`, dann `members[]` rekursiv in `<ul>`.
  - `render_spec_view(spec_object)` baut `<details class="spec-view" open>`
    mit Summary "Spec", file_spec-Block, Symbol-Liste, Warnings am Ende.
    `details open` = aufgeklappt by default; `<summary>` bekommt CSS
    `cursor: pointer`. `spec_object_has_renderable_content()` filtert
    null/error/leere Schemas defensiv (auch wenn das Backend schon filtert).
  - `load_path()`: `article_element.innerHTML = render_spec_view(data.spec) + data.html;`.
    Wenn keine Spec-View: leerer String + html, also identisches Verhalten
    wie vorher.
- `app/index.php` (+14 Zeilen): CSS-Regeln `.spec-view`, `.spec-view-summary`,
  `.spec-view-body`, `.spec-view-file-spec`, `.spec-view-symbols`,
  `.spec-view-members`, `.spec-view-symbol`, `.spec-view-signature`,
  `.spec-view-spec-line`, `.spec-view-warnings`, `.spec-view-warning`. Klassen
  alle `spec-view-`-praefixiert, kollidieren nicht mit Tree/Article.
  `cursor: pointer` auf `.spec-view-summary` (Ticket-Pflicht). System-UI-Font,
  dezente Hintergruende (#f0f0f0/#fafafa), monospace nur fuer Signaturen.

### Lint

- `php -l app/file.php` -> `No syntax errors detected`.
- `php -l app/index.php` -> `No syntax errors detected`.
- `node -c app/_share/js/content_loader.js` -> sauber.

### Backend-Smoketest (Server: `php -S 127.0.0.1:8099 -t app`, am Ende beendet)

- `curl http://127.0.0.1:8099/file.php?path=app/user/data/User.php`:
  ```
  ok: True | path: app/user/data/User.php
  spec.file_spec: ['Authenticated platform user; admin flag controls privileged endpoints.']
  spec.symbols[0].name: User
  spec.symbols[0].members count: 6
  member names: ['username', 'password', 'email', 'email_verified', 'language', 'is_admin']
  warnings: []
  ```
- `curl http://127.0.0.1:8099/file.php?path=app/user/actions/CreateUserAction.php`:
  ```
  ok: True | path: app/user/actions/CreateUserAction.php
  spec.file_spec: ['Admin-only action that validates inputs and inserts a new User row, exposing the created id.']
  spec.symbols[0].name: CreateUserAction
  member kinds/names: [('property', 'created_user_id'), ('method', '__construct'), ('method', 'execute')]
  execute.signature: static function execute(string $username, string $password, string $email, string $is_admin = "0"): self
  execute.spec lines: 11
  warnings: []
  ```
- `curl http://127.0.0.1:8099/file.php?path=README.md`:
  `spec is null: True`.
- `curl http://127.0.0.1:8099/file.php?path=app/_share/db.php`:
  `spec is null: True` (Klasse `db` wird vom Parser zwar gefunden, hat aber
  keine `@spec`-Bloecke — Backend-Filter dreht es zu null).
- `curl http://127.0.0.1:8099/index.php` -> HTTP 200, HTML enthaelt
  `.spec-view`-CSS-Regeln (per `grep -c "spec-view"` 14 Treffer im Header-CSS).
- Server am Ende per `TaskStop` beendet, Port 8083 nicht angefasst.

### Renderer-Entscheidungen / Edge-Cases

- **Property-Signatur**: Parser-Schema gibt `name`/`type`/`default` separat,
  keine `signature`. Ich synthetisiere `public <type> $<name> = <default>`.
  `public` ist pragmatisch hardcoded; Visibility-Tracking im Parser ist
  out of scope hier (M005/0002 koennte das nachreichen, falls gewuenscht).
- **Method/Function-Signatur**: kommt vom Parser als Source-String und wird
  unveraendert (escaped) in `<code>` gerendert.
- **XSS**: alle Spec-Inhalte gehen durch `escape_html()` — DOM-textContent
  via temporaerem Div, das ist die kanonische Methode und schlaegt manuelles
  `&lt;`/`&amp;`-Escaping. Strukturelles HTML (Tags, Klassen) wird per
  Stringkonkatenation gebaut; nur Inhalt ist user-/spec-stammend.
- **Members rekursiv**: `render_symbol()` ruft sich fuer `members[]` selbst
  auf — funktioniert fuer class -> property/method, method -> local-spec.
- **Warnings**: separater `<div>` am Ende der Spec-View, falls vorhanden.
- **Backend-Filter**: schickt null statt Error-Schema durch (vendor /
  unsupported). Pragmatische Wahl, das Frontend darf zwar Errors, ist aber
  optisch keine Verbesserung. Im Frontend zusaetzlich `spec_object_has_renderable_content()`
  als defensive Sicherung.

### Streu-File-Check / Cleanup

- `find . -name "app.sqlite*" -not -path "./pm/*" -not -path "./.git/*"` ->
  nur `./app.sqlite` (kanonisch).
- Kein `php -S` mehr aktiv (TaskStop bestaetigt).
- Keine Tempfiles in `/tmp/spec_parser_*` o.ae. erstellt.

### Out-of-scope eingehalten

- JS-Parser nicht angefasst (M005/0003).
- Fixture-Tests nicht gebaut (M005/0004).
- `.spec`-Datei-Anzeige unveraendert: `.spec`-Endung ist nicht in
  `.php`/`.js`, also bleibt `spec_payload` null und der Plaintext-`<pre>`-Render
  greift wie vorher.
- Andere Dateien nicht migriert.
- Kein Caching, Re-Parse pro Request ist OK.
- Persistierung des Aufgeklappt-Status: nicht implementiert (`details open`
  als Default reicht).
