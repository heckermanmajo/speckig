# 0003 — Specs für app/_share/exceptions/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder Exception-Klasse:
  - `ActionInputError.spec`
  - `BadCredentialsError.spec`
  - `BadStateError.spec`
  - `IdNotFoundError.spec`
  - `NeedsLoginError.spec`
  - `NotAllowedError.spec`
  - `SystemError.spec`
  - `UserError.spec`
  - `UserInputError.spec`
- Exceptions haben oft keine eigenen Funktionen — `functions: []` weglassen.
- `purpose` reicht meistens, plus ggf. eine `condition` à la "wird geworfen wenn …".
- Schema kann minimal sein: nur `file` und `purpose`.
