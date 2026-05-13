# 0003 — JS-Helpers + Collapse-State persistieren

Erstes JS im Projekt. Legt die Konventionen, die spätere Tickets nutzen, und löst das erste konkrete Problem: `<details>`-Zustand verschwindet beim Reload.

See: pm/decisions/0004-ux-policy.md

## Done when
- `app/_share/js/helpers.js` existiert. Style: BSD-Klammern, snake_case, `let what_cond_means = …; if (what_cond_means) { … }`. Eine Sektion mit Header-Kommentar.
- Helper-Funktionen: `local_get(key, fallback)`, `local_set(key, value)` (Wrapper um localStorage, JSON-safe).
- `app/_share/js/tree_collapse.js` lädt nach helpers.js, läuft auf `DOMContentLoaded`:
  - Beim Start: alle `<details data-path="…">` werden auf den in localStorage gespeicherten Zustand gesetzt (default: open).
  - Beim `toggle`-Event: aktuellen Zustand in localStorage schreiben unter Key `speckig.collapse.<path>`.
- `app/index.php` rendert jedes `<details>` mit einem `data-path="<rel-pfad>"`-Attribut.
- `index.php` lädt beide JS via `<script src="/_share/js/helpers.js"></script>` und `<script src="/_share/js/tree_collapse.js"></script>` am Body-Ende.
- Smoketest: im Browser einen Ordner zuklappen, F5 — bleibt zu. `pm/decisions/` aufklappen, F5 — bleibt offen.
