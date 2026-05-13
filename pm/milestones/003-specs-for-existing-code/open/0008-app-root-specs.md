# 0008 — Specs für app/ Root und data_initializer

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder dieser Files:
  - `app/index.php.spec` ← Speckig-Layout (Tree links, Article rechts, Pfad-Validation, Header).
  - `app/index_mobile.php.spec` ← Mobile-Variante (Login).
  - `app/api.php.spec` ← CSRF-geschützter Action-Dispatch.
  - `app/file.php.spec` ← JSON-Endpoint für Datei-Inhalt.
  - `app/_share/data_initializer.php.spec` ← CLI-only, baut Tabellen für registrierte DataClasses.
- Vendored Files (`app/_share/vendor/`) NICHT spec'en (Out of scope laut Decision 0005).
- Damit ist Coverage abgeschlossen: alle non-vendored PHP-Files haben eine `.spec`.
