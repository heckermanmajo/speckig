# 0001 — Parsedown PHP 8.4+ deprecation warnings

Symptom: jedes Markdown-Render gibt zwei Warnungen aus —
`Parsedown::blockSetextHeader(): Implicitly marking parameter $Block as nullable is deprecated`
und dasselbe für `blockTable()`. Sichtbar im HTML-Output und in `error_log`.

Ursache: Parsedown 1.7.4 wurde 2019 für PHP <8.4 geschrieben. Seit
PHP 8.4 müssen Default-`null`-Parameter explizit `?Type` deklariert sein.

See: pm/decisions/0002-php-infra.md

## Done when
- Beide Methodensignaturen in `app/_share/vendor/Parsedown.php` nutzen `?array $Block = null`.
- Render einer Markdown-Datei mit Setext-Header und Tabelle gibt keine Deprecation-Warnung mehr aus.
- Patch ist im File-Header dokumentiert (kein separates PATCHES.md).

## Done
- Header von `Parsedown.php` um einen "Local patches"-Block ergänzt, der den Patch mit Datum und Bug-Referenz nennt.
- Zwei Signaturen umgestellt: Zeile 715 `blockSetextHeader` und Zeile 853 `blockTable` von `array $Block = null` auf `?array $Block = null`.
- `blockCode` Zeile 320 hat `$Block = null` ohne Typ — PHP meckert da nicht, deshalb nicht angefasst.
- Verifikation: `php -d error_reporting=E_ALL -r '...Parsedown->text(setext+table)'` rendert sauberes HTML ohne Warnungen.
- Vendor-Policy in [[decisions/0002-php-infra]] sagt "Originalheader bleibt". Ist eingehalten — der Originalheader steht oben, der Patch-Hinweis ist ein zusätzlicher Block darunter; nichts gelöscht.
