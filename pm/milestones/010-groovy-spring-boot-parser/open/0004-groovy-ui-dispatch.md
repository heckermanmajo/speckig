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

(append after work)
