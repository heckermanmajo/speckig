# 0004 — data_initializer.php fix

See: pm/decisions/0002-php-infra.md
Blocked by: 0001

Der einkopierte `data_initializer.php` referenziert `tenant\…` und `network\…` Klassen, die nicht existieren — CLI knallt sofort.

## Done when
- `$data_classes` enthält nur Klassen, die im Repo existieren (mindestens `User`).
- `php app/_share/data_initializer.php` läuft ohne Fatal Error durch.
- Tabelle `User` ist in `app.sqlite` angelegt.

## Done
- `app/_share/data_initializer.php`: `use`-Statements für `tenant\…` und `network\…` entfernt, `$data_classes` auf `[User::class]` reduziert. Header, CLI-Guard, Autoloader, Loop-Logik unverändert.
- Verifiziert mit `find app -name "*.php" -path "*/data/*"`: nur `app/user/data/User.php` existiert — der Rest gehört zu späteren Milestones.
- `php app/_share/data_initializer.php` läuft sauber durch:
  `creating table user\data\User... ok` / `done.` / exit 0.
- `app.sqlite` enthält genau eine Tabelle: `User` (Spalten `id, username, password, email, email_verified, language, is_admin, created_at`).
- Smoketest `curl http://127.0.0.1:8080/` mit laufendem `php -S` liefert HTTP 200 mit Login-HTML, keine PDOException mehr (init.php seedet den Admin-User via `db::save`).
- `app.sqlite` bleibt out-of-repo (`.gitignore` matcht).
