# 0007 — Specs für app/user/ Pages + Snippets

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder dieser Files:
  - `app/user/index.php.spec`
  - `app/user/index_mobile.php.spec`
  - `app/user/p/one_profile/index.php.spec`
  - `app/user/p/one_profile/index_mobile.php.spec`
  - `app/user/snippets/search_results.php.spec`
- Pages sind Templates — `purpose` beschreibt, was gerendert wird; `functions: []` meist weglassen.
- Mobile-Pages: Spec darf 1:1 zu Desktop-Variante zeigen mit Hinweis "Mobile-Variante von …".
