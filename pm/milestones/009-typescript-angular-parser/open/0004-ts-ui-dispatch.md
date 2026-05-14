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

(append after work)
