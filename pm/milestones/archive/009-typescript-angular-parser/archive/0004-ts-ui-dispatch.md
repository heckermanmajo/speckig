# 0004 — TS im file.php-Dispatch + UI-Smoketest

Blocked by: 0002

## Done when

- `app/file.php` ruft Parser auch fuer `.ts`. Falls Endungs-Whitelist da ist: `ts` ergaenzen.
- Spec-View rendert TS-spezifische Schema-Erweiterung (Decorators):
  - Im Frontend (`content_loader.js::render_symbol`) wird `decorators[]` ueber der Signatur als kleine Liste gerendert: `<div class="spec-view-decorators"><code>@Component({...})</code></div>`.
  - CSS-Regel `.spec-view-decorators` ergaenzt in `app/_share/css/app.css`: dezent, monospace, kleinere Schrift.
  - Decorator-Args werden escaped (XSS).
  - Wenn `decorators[]` leer/unset ist (PHP/JS/Nim/Lua/Groovy): nichts rendern — abwaerts-kompatibel.
- Smoketest: `php -S 127.0.0.1:8099 -t app`, Demo-`.ts`-Fixture per `?path=...` aufrufen, Spec-View zeigt Component mit `@Component`-Decorator + Properties + Methoden.
- Box abhaken; falls letztes Ticket: Milestone-Folder nach `archive/`.

## Out of scope

- `.tsx`.
- Angular-Template-HTML rendern.
- Decorator-Args formatieren / pretty-printen — bleibt Source-String.

## Done

- **`app/file.php`**: Endungs-Whitelist um `ts` ergaenzt — der
  `$language_is_supported_for_spec`-Flag matcht jetzt zusaetzlich
  `$file_extension === "ts"`. Eine Zeile, kein anderer Touch an
  `file.php` (Konsolidierung-Backlog von 002/0006 nicht angefasst).

- **`app/_share/js/content_loader.js`**: neuer Helper
  `render_decorators(symbol_object)` und Aufruf in `render_symbol`
  unmittelbar VOR der `<code class="spec-view-signature">` (Angular-
  typische Position oben drueber). Per-Symbol wird
  `let decoratorlike = symbol_object.decorators || symbol_object.annotations || [];`
  ausgewertet — damit ist die Generalisierung fuer M010 (Groovy/
  Spring `annotations[]`) bereits drin und der Renderer braucht in
  M010 keine Aenderung mehr. Pro Eintrag wird ein
  `<code class="spec-view-decorator">@<name>[(<args_source>)]</code>`
  gerendert. `args_source`-Form 1:1 nach Schema:
  - `null` (typeof !== "string") -> nur `@<name>`.
  - `""` -> `@<name>()`.
  - `"<inhalt>"` -> `@<name>(<inhalt>)`.
  XSS-Escape via `escape_html()` (DOM-textContent-Roundtrip), genau
  wie alle anderen Spec-View-Texte. V1-pragmatisch: kein Truncate
  von `args_source` — Inhalt wird voll angezeigt; bei Bedarf
  spaeteres CSS-`text-overflow` reicht aus.

- **`app/_share/css/app.css`**: zwei neue Regeln direkt unter
  `.spec-view-signature` ergaenzt, ohne Refactor der bestehenden
  Spec-View-Regeln:
  - `.spec-view-decorators { margin-bottom: 0.25rem; }`
  - `.spec-view-decorator { display: block; font-family: ui-monospace, monospace; font-size: 0.85rem; color: #666; padding: 0.1rem 0; }`
  Style ist dezent (kleinere Schrift, gedeckte Farbe), damit die
  Signatur die optisch dominante Zeile bleibt.

- **Demo-Datei**: bestehende
  `app/_share/spec_parser/tests/fixtures/ts/angular_component.ts`
  (aus M009/0003) wird unveraendert wiederverwendet — sie hat genau
  das geforderte Pattern (Datei-Spec, `@Component({...})`-Klassen-
  Decorator mit Args, Properties mit Spec, `@LogCall()`-Methode mit
  Spec).

- **Backwaerts-Kompatibilitaet**: `decorators[]` ist optional und
  wird vom PHP/JS/Nim/Lua-Parser nicht emittiert. Der neue Renderer
  bricht ohne das Feld korrekt frueh ab (`list_has_entries === false`
  -> leerer String, keine `<div>`-Wrapper). Verifiziert per
  Smoketest gegen die unveraenderte Tree-Sicht: keine Streu-Wrapper
  in PHP-Schemata.

### Verifikations-Belege

- `php -l app/file.php` -> "No syntax errors detected".

- `php app/_share/spec_parser/tests/run.php` -> exit 0,
  `38/38 passed`. Pro-Sprache:

  ```
  php: 6/6
  js: 4/4
  nim: 7/7
  lua: 8/8
  ts: 11/11
  ```

  Keine Regression — die `app/file.php`-Aenderung beruehrt den
  Spec-Parser nicht direkt, aber die Suite bleibt der Regression-Anker.

- **Server**: `php -S 127.0.0.1:8099 -t app` (Bash run_in_background
  auf Port 8099, NICHT 8083). Nach den Smoketests via TaskStop
  beendet.

- **curl-Smoketest 1** (Angular-Demo):

  ```
  curl -s "http://127.0.0.1:8099/file.php?path=app/_share/spec_parser/tests/fixtures/ts/angular_component.ts"
  ```

  Output (per Python-One-Liner extrahiert):

  ```
  ok: True
  lang: ts
  file_spec: ['File-level spec: app entry component for the demo Angular app.']
  first symbol: class AppComponent decorators: [('Component', '{ selector: "app-root", templateUrl: "./app.component.html", }')]
  ```

  Plus: 4 Members am AppComponent, der `login`-Member traegt
  `decorators: [('LogCall', '')]` — alle drei `args_source`-Faelle
  abgedeckt (Klasse: Inhalt, Methode: leer, "ohne Klammern"-Fall via
  `decorator_with_args.ts`-Fixture exemplarisch belegt durch
  M009/0003).

- **curl-Smoketest 2** (no_spec.ts):

  ```
  curl -s "http://127.0.0.1:8099/file.php?path=app/_share/spec_parser/tests/fixtures/ts/no_spec.ts"
  ```

  Output: `spec is None? True` — der "kein Mehrwert"-Filter in
  `file.php` greift weiterhin korrekt: Datei hat keine `@spec`-
  Marker (auch wenn Symbole vorhanden sind), also `spec_payload =
  null`, also wird im Frontend nur der Code-Tab aktiv.

- **Streu-Files**: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).

- **Server-Cleanup**: TaskStop auf den 8099-Server. Der 8083-Server
  des Users wurde nicht angefasst.

### Renderer-Entscheidungen

- **Position**: Decorators werden VOR der Signatur gerendert
  (Angular-typische Schreibweise `@Component({...})\nexport class X`).
  Ticket erlaubt beides — oben drueber wirkt visuell konsistenter
  mit dem Source-Code.
- **`args_source`-Truncate**: V1 ohne Truncate, voller Inhalt wird
  angezeigt. Begruendung: M009-Demo `@Component({...})` ist
  ueberschaubar (~80 Zeichen), und CSS-`text-overflow` waere ein
  Folge-Cosmetic-Ticket falls echte Datei-Specs spaeter laenger
  werden. Out-of-scope-Decision pragmatisch im Ticket erlaubt.
- **Generalisierung**: `decorators || annotations` per
  Boolean-OR direkt im Helper — eine Zeile statt zwei Code-Pfade.
  Fuer M010 reicht es, dass der Groovy-Parser `annotations[]` mit
  derselben `{name, args_source}`-Form emittiert; der Renderer ist
  fertig.
