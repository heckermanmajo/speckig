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
