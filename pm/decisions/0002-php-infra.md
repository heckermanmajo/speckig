# 0002 — PHP infra

- PHP-Mindestversion ist 8.5.
- Aller PHP-Code lebt unter `app/`, das ist auch DocRoot für `php -S`.
- Entry-Point ist `app/index.php`, Helfer unter `app/_share/`.
- Kein Composer; externe Libs als einzelne Files unter `app/_share/vendor/` mit Originalheader.
- Eigener Autoloader in `app/_share/init.php`, kein PSR-4-Tooling.
- Jede PHP-Datei beginnt mit `declare(strict_types=1);`.
- Indent ist 4 Spaces, keine Tabs.
- Klammerstil ist BSD: `{` und `}` stehen auf eigener Zeile.
- Bedingungen werden zuerst als benannte Variable berechnet, dann verzweigt: `$what_cond_means = …; if ($what_cond_means) { … }`.
- `assert()` ist im Dev-Modus aktiv, für Invariants, nicht für User-Input-Validierung.
- Logging geht über `error_log()`, kein eigener Logger.
- Naming: Klassen `PascalCase`, Funktionen/Methoden `camelCase`, Variablen `snake_case`.
- UI ist „konservatives PHP": keine SPA, kaum CSS, soviele semantische HTML5-Elemente wie möglich.
- Login-/User-Skelett aus dem einkopierten Code bleibt im Repo, wird aber in M001 von keiner aktiven Route geladen.
- Details, Beispiele und Begründung in `pm/how-to/code_style.md`.
