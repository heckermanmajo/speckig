file: search_results.php
purpose: Admin-only HTML snippet that returns an <ul> of user search hits for a picker dialog, loaded via load_snippet_into.
conditions:
  - "calls app::enforce_login() then app::enforce_plattform_admin() — non-admins are rejected before any output"
  - "reads $_GET['q'], trims it; non-string falls back to empty"
  - "sends Content-Type: text/html; charset=utf-8"
  - "empty query renders a hint <ul> and exits without touching the DB"
  - "queries User via db::select with a bound LIKE pattern on username/email, ORDER BY username ASC, LIMIT 20 — no SQL injection"
  - "zero hits renders a 'Keine Treffer.' hint <ul> and exits"
  - "each <li> carries an onclick that calls parent-defined select_user_for_picker(id, label)"
  - "label is JSON-encoded with JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT and then htmlspecialchars'd so the attribute stays well-formed"
  - "visible label text is run through app::escape before output"
  - "parent snippet must define select_user_for_picker(id, label) — otherwise click is a no-op"
