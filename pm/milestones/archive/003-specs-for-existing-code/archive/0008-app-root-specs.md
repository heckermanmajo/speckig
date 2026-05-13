# 0008 — Specs für app/ Root und data_initializer

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder dieser Files:
  - `app/index.spec` ← Speckig-Layout (Tree links, Article rechts, Pfad-Validation, Header).
  - `app/index_mobile.spec` ← Mobile-Variante (Login).
  - `app/api.spec` ← CSRF-geschützter Action-Dispatch.
  - `app/file.spec` ← JSON-Endpoint für Datei-Inhalt.
  - `app/_share/data_initializer.spec` ← CLI-only, baut Tabellen für registrierte DataClasses.
- Vendored Files (`app/_share/vendor/`) NICHT spec'en (Out of scope laut Decision 0005).
- Damit ist Coverage abgeschlossen: alle non-vendored PHP-Files haben eine `.spec`.

## Done
- Fünf neue Specs geschrieben:
  - `app/index.spec` — Speckig-Layout. Top-Level-Bootstrap (SPECKIG_ROOT-resolve, 3-Schicht-Pfad-Validation, XSS-Härtung für Header-Label) + `functions:` für `count_visible_children` und `render_tree`.
  - `app/index_mobile.spec` — Legacy-Mobile-Login-Skeleton; bewusst minimal (Top-Level-`conditions:` für Mobile-Redirect, Login-Redirect, Head/Footer-only Body).
  - `app/api.spec` — CSRF-geschützter Action-Dispatcher. Top-Level-`conditions:` listet die Sicherheits-Schichten (action-param, hash_equals-CSRF, regex-Whitelist für Action-Namen, `class_exists`, `is_subclass_of` Action, Strip von Transport-Feldern aus `$_REQUEST`/`$_POST`/`$_GET`, Throwable-Catchall).
  - `app/file.spec` — JSON-Endpoint. Top-Level-`conditions:` für 3-Schicht-Pfad-Validation, 400-bei-Fehler, .md→Parsedown vs. sonst→`<pre>`-escape, Success-Envelope-Shape.
  - `app/_share/data_initializer.spec` — CLI-only DB-Init. Top-Level-`conditions:` für CLI-Guard (403 + exit 1), CLI-Autoloader-Workaround, inline-`$data_classes`-Registry, `db::create_and_update_table`-Loop, Exit-Code reflektiert Fehlerzahl.
- YAML-Validität: alle 5 Files passieren `python3 -c "yaml.safe_load(...)"`.
- Coverage-Check: `find app -name "*.php" -not -path "*/vendor/*"` ohne `.spec`-Pendant ergibt `MISSING:0`. **Alle** non-vendored PHP-Files haben jetzt eine Spec.
- Kein PHP-Code editiert (Out-of-scope laut M003-Briefing).
- Vendor-Files (`app/_share/vendor/Parsedown.php`) ohne Spec — Decision 0005.
- **M003-Abschluss**: dies war die letzte Box. `milestone.md` Status `active` → `done`, alle Tickets `[x]` mit `archive/`-Pfaden. Folder-Move `pm/milestones/003-specs-for-existing-code` → `pm/milestones/archive/003-specs-for-existing-code` im selben Commit (siehe `pm/how-to/milestones.md`).
