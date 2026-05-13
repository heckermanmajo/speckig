# 0003 — data_initializer.php fix

See: pm/decisions/0002-php-infra.md
Blocked by: 0001

Der einkopierte `data_initializer.php` referenziert `tenant\…` und `network\…` Klassen, die nicht existieren — CLI knallt sofort.

## Done when
- `$data_classes` enthält nur Klassen, die im Repo existieren (mindestens `User`).
- `php app/_share/data_initializer.php` läuft ohne Fatal Error durch.
- Tabelle `User` ist in `app.sqlite` angelegt.
