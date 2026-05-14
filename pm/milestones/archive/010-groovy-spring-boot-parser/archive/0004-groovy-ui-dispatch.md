# 0004 — Groovy im file.php-Dispatch + UI-Smoketest

Blocked by: 0002

## Done when

- `app/file.php` ruft Parser auch fuer `.groovy`. Falls Endungs-Whitelist da: `groovy` ergaenzen.
- Spec-View rendert die Annotation-Schema-Erweiterung — falls M009/0004 schon das `decorators[]`-Rendering eingefuehrt hat, generalisier das zu `annotations[] || decorators[]` (beide Felder gleich rendern, weil sie semantisch dieselbe UI-Form haben). Andernfalls: rendere `annotations[]` analog `decorators[]` aus M009 als kleine Liste ueber der Signatur (`<div class="spec-view-annotations">`).
- CSS-Regel ggf. ergaenzen (oder `.spec-view-decorators` wiederverwenden).
- Smoketest: `php -S 127.0.0.1:8099 -t app`, Demo-`.groovy`-Fixture per `?path=...`, Spec-View zeigt RestController mit `@RestController`/`@GetMapping`-Annotations + Methoden.
- Box abhaken; falls letztes Ticket: Milestone-Folder nach `archive/`.

## Out of scope

- Java.
- Spring-Boot-spezifisches UI (Endpoint-Liste, Bean-Graph).
- Annotation-Args formatieren — bleibt Source-String.

## Done

- **`app/file.php`-Dispatch**: `$language_is_supported_for_spec`-Whitelist
  um `|| $file_extension === "groovy"` ergaenzt (eine Zeile, neben den
  bestehenden `php`/`js`/`nim`/`lua`/`ts`-Vergleichen). Der restliche
  Datenfluss bleibt unveraendert: `spec_parser::parse()` dispatcht ueber
  die Endung an `groovy_parser::parse()` (M010/0001), das Resultat
  durchlaeuft den vorhandenen `$spec_walker`-Filter (Datei-Spec ODER
  irgendein Symbol/Member mit Spec-Zeile -> rendern; sonst `null`).
- **Renderer-Verifikation** (kein Code-Change): `render_decorators` in
  `app/_share/js/content_loader.js` greift bereits auf
  `symbol_object.decorators || symbol_object.annotations || []` zu
  (Zeile 300, vorbereitet in M009/0004). Der Fallback rendert
  Groovy-`annotations[]` 1:1 wie TS-`decorators[]` als
  `<div class="spec-view-decorators">` mit `<code class="spec-view-
  decorator">`-Eintraegen. `args_source`-Behandlung
  (`null` -> `@Name`, `""` -> `@Name()`, `"<inhalt>"` -> `@Name(<inhalt>)`)
  funktioniert ohne sprachspezifische Verzweigung. Keine CSS-Aenderung
  noetig — `.spec-view-decorators` wird wiederverwendet.

### Verifikations-Belege

- `php -l app/file.php` -> "No syntax errors detected".
- `php app/_share/spec_parser/tests/run.php` -> exit 0:
  - php: 6/6, js: 4/4, nim: 7/7, lua: 8/8, ts: 11/11, groovy: 10/10
  - Gesamt: `48/48 passed`. Keine Regression.
- `php -S 127.0.0.1:8099 -t app` (Background) gestartet.
- `curl -s "http://127.0.0.1:8099/file.php?path=app/_share/spec_parser/
  tests/fixtures/groovy/spring_rest_controller.groovy"`:
  - `ok: True`
  - `lang: groovy`
  - `first symbol: class FooController annotations:
    [('RestController', None), ('RequestMapping', '"/api"')]`
- `curl -s ".../no_spec.groovy"` -> `spec is None? True` (Filter greift,
  kein Spec-View fuer Datei ohne `@spec`-Marker).
- Server gestoppt (`TaskStop`).
- Streu-Files: `find . -name "app.sqlite*" -not -path "./pm/*"
  -not -path "./.git/*"` -> nur `./app.sqlite` (kanonisch).

### Out-of-scope eingehalten

- Keine Groovy-Code-Migration im Repo.
- Keine Spring-Boot-spezifischen UI-Features (Bean-Graph, Endpoint-Liste).
- Keine Parser-Aenderungen.
- README unveraendert.
- Kein Java-Parser.
