# 0004 — Specs für app/_share/html/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `app/_share/html/document.spec` ← head() + footer()
- `app/_share/html/cards.spec` ← was auch immer drin ist
- Beide Specs erwähnen: Login-Nav, CSRF-Meta, globales Dialog-Element (laut Code).
- Out of scope: data_initializer.php — kommt in Ticket 0008.

## Done

- 2 Spec-Files unter `app/_share/html/`:
  - `document.spec`: 2 Funktionen (`head`, `footer`). `head` deckt CSRF-Meta (`$_SESSION['csrf_token']` → `<meta name='csrf-token'>`), Login-aware Nav (Dashboard/Username vs. Login, Admin-Link bei `is_admin === 1`) und `app::escape` für Username ab. `footer` deckt das einmalige `<dialog id='global-dialog'>` + Runtime-Füllung durch `/sharejs/snippet.js` ab.
  - `cards.spec`: 2 Funktionen (`get_error_card`, `get_info_card`) — beide rein renderend, keine Bedingungen wert (Bodies sind `# ...`-Stubs).
- YAML-Check `python3 -c "yaml.safe_load(...)"` für beide grün.
- `grep -i "csrf\|dialog" app/_share/html/document.spec` zeigt 6 Treffer (CSRF-Meta + Dialog-Element jeweils in `does:` und `conditions:` belegt). Login-Nav ebenfalls erwähnt (purpose + 2 conditions).
- `cards.spec` erwähnt CSRF/Dialog/Login bewusst nicht — der Code tut das auch nicht ("laut Code" aus Done-when). Pflicht-Erwähnungen gehören zu `document.spec`.
- Sprache Englisch, konsistent mit M003/0002 + 0003.
- Kein PHP editiert. Streu-File-Check: nur kanonischer `app.sqlite`-Pfad.
