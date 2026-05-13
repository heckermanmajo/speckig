# 0003 — Specs für app/_share/exceptions/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `.spec` neben jeder Exception-Klasse:
  - `ActionInputError.php.spec`
  - `BadCredentialsError.php.spec`
  - `BadStateError.php.spec`
  - `IdNotFoundError.php.spec`
  - `NeedsLoginError.php.spec`
  - `NotAllowedError.php.spec`
  - `SystemError.php.spec`
  - `UserError.php.spec`
  - `UserInputError.php.spec`
- Exceptions haben oft keine eigenen Funktionen — `functions: []` weglassen.
- `purpose` reicht meistens, plus ggf. eine `condition` à la "wird geworfen wenn …".
- Schema kann minimal sein: nur `file` und `purpose`.
