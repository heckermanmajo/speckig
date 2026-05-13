# 0006 — Server-Render aufräumen

Nach 0005 macht `index.php` den Content-Render nicht mehr — JS übernimmt. Aufräumen, kein toter Code.

See: pm/decisions/0004-ux-policy.md
Blocked by: 0005

## Done when
- `app/index.php` rendert rechts initial nur den `Datei links auswählen.`-Hinweis.
- Die Markdown-Render-Logik (`Parsedown`-Aufruf, `<pre>`-Branch) wandert komplett nach `app/file.php` (war schon in 0004 dorthin gewachsen) — `index.php` lädt Parsedown nicht mehr.
- `index.php` ist deutlich kleiner. `wc -l app/index.php` zeigt eine Reduktion (verglichen mit dem Stand vor M002).
- Smoketest: `curl -s http://127.0.0.1:8080/?path=pm/decisions/0002-php-infra.md` enthält **nicht** das gerenderte Markdown. Es enthält den leeren Article + den Hinweis (JS würde es dann nachladen, aber curl führt kein JS aus).
- `php -l` auf beide Files clean.
