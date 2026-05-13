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

## Done
- Neue Files: `app/_share/js/helpers.js`, `app/_share/js/tree_collapse.js` (erstes JS im Projekt).
- `app/index.php`: `render_tree()` haengt jedem Ordner-`<details>` ein `data-path="<rel-pfad>"` an (escape via `app::escape`, Pfad ohne trailing slash, relativ zu `$speckig_root_abs`). Vor `</body>` werden `helpers.js` und `tree_collapse.js` in dieser Reihenfolge per `<script>` geladen — kein `defer` noetig, weil Body-Ende.
- JS-Style-Entscheidung (legt die Konvention fuer alle kuenftigen JS-Files): **BSD-Klammern auch in JS**, wie Decision 0004 woertlich verlangt — `function fn()` und `{` auf eigener Zeile. Das geht in JS syntaktisch problemlos; der einzige bekannte Stolperstein ist `return <newline> {…}` (ASI-Falle), den umgehen wir, indem `return`-Ausdruecke immer auf einer Zeile bleiben (kein `return\n{`). Weiter: `snake_case` fuer Variablen und Funktionen, `let`/`const` statt `var`, kein `import`/`export` (Top-Level-Script), `let what_cond_means = …; if (what_cond_means) { … }`-Pattern (`stored_is_collapsed`, `no_state_stored`, `should_be_open`, `path_is_missing`, `is_collapsed_now`).
- localStorage-Semantik unter Key `speckig.collapse.<rel-path>`: gespeichert wird **boolean = "ist zugeklappt"** — `true` bedeutet "User hat zugeklappt" → `details.open = false`; `false` bedeutet "User hat aufgeklappt" → `details.open = true`. Fehlender Eintrag (`null`) → kein Eingriff, Server-Default (`<details open>`) bleibt. Begruendung: `true` = "abweichender Zustand vom Default" liest sich beim Debuggen am natuerlichsten, und die zwei Branches `should_be_open = stored === false` machen explizit, dass `null` neutral ist.
- `tree_collapse.js` ist in eine IIFE gewickelt, um Hilfsfunktionen (`key_for_path`, `apply_stored_state`, `attach_toggle_listener`, `init_tree_collapse`) nicht global zu leaken. Nur `local_get`/`local_set` aus `helpers.js` sind global — das ist beabsichtigt fuer kuenftige UX-Scripts.
- `helpers.js` schluckt fehlende/blockierte `localStorage`-Zugriffe (private mode, Quota) per `try/catch` und liefert `fallback` — UX-Layer bleibt lautlos, keine Console-Errors fuer den User.
- Verifikation:
  - `php -l app/index.php` → `No syntax errors`.
  - `node --check` fuer beide JS-Files → ohne Fehler.
  - Test A (server liefert JS): `curl -sI http://127.0.0.1:8080/_share/js/helpers.js` → `HTTP/1.1 200 OK`, `Content-Type: application/javascript`. Dito `tree_collapse.js`.
  - Test B (data-path im HTML): `curl -s http://127.0.0.1:8080/ | grep -oE 'data-path="[^"]*"' | head` zeigt `data-path="app"`, `data-path="app/_share"`, `data-path="app/_share/data"`, … `data-path="pm/decisions"` etc. — Pfade matchen das `?path=`-Format.
  - Test C (script-tags in richtiger Reihenfolge): `grep -oE 'src="/_share/js/[a-z_]+\.js"'` → `helpers.js` zuerst, dann `tree_collapse.js`.
  - Test D (funktional, Node + localStorage-Mock): VM-Sandbox laedt `helpers.js`, `local_set("test", {a:1,b:[2,3]})` + `local_get("test", null)` roundtrip ok, fehlender Key → fallback ok, kaputter JSON-String im Storage → fallback ok. `tree_collapse.js` selbst nicht headless getestet (kein DOM-Mock) — die DOM-Logik (querySelectorAll + toggle-Listener) ist im Browser manuell zu pruefen: Ordner zuklappen → F5 → bleibt zu; aufklappen → F5 → bleibt offen.
- Cleanup: eigener 8080-Server via `TaskStop` beendet, fremde 8083-Session unangetastet. Streu-File-Check zeigt nur kanonische `app.sqlite`.
