# 0004 — UX policy (supersedes 0002-UI)

- Supersedes die UI-Zeile in `pm/decisions/0002-php-infra.md` ("UI ist konservatives PHP: keine SPA, kaum CSS, soviele semantische HTML5-Elemente wie möglich").
- JS ist erlaubt — beschränkt auf den UX-Layer: Tree-Collapse-State (localStorage), AJAX-Content-Load, History (pushState).
- Server bleibt PHP — Templates und Datenflüsse werden serverseitig gerendert; JS macht keinen eigenen Datenmodellbau.
- Kein npm, kein Bundler, kein TypeScript — JS-Files werden einzeln unter `app/_share/js/` eingecheckt, als `<script>` geladen.
- JS-Style folgt dem PHP-Style: BSD-Klammern, `snake_case` für Variablen und Funktionen, `$what_cond_means`-Pattern (ohne `$`), eingecheckte Vendor-Files mit Originalheader.
- Kaum CSS bleibt — inline `<style>` in `index.php`, kein Build-Step.
- Semantische HTML5-Elemente bleiben Pflicht.
