# 0007 — Specs für app/user/ Pages + Snippets

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder dieser Files:
  - `app/user/index.spec`
  - `app/user/index_mobile.spec`
  - `app/user/p/one_profile/index.spec`
  - `app/user/p/one_profile/index_mobile.spec`
  - `app/user/snippets/search_results.spec`
- Pages sind Templates — `purpose` beschreibt, was gerendert wird; `functions: []` meist weglassen.
- Mobile-Pages: Spec darf 1:1 zu Desktop-Variante zeigen mit Hinweis "Mobile-Variante von …".

## Done
- Added five specs next to their templates:
  - `app/user/index.spec`
  - `app/user/index_mobile.spec`
  - `app/user/p/one_profile/index.spec`
  - `app/user/p/one_profile/index_mobile.spec`
  - `app/user/snippets/search_results.spec`
- Page specs use top-level `conditions:` (no `functions:` block) for the auth/mobile-redirect guards, mirroring `init.spec`.
- Mobile-Pages reference their desktop sibling in `purpose:` ("Mobile variant of …").
- `search_results.spec` documents the admin gate, empty/zero-hit branches, bound LIKE query, and the JSON+htmlspecialchars escaping chain for the onclick attribute.
- YAML validity checked for all 5 files via `yaml.safe_load`.
