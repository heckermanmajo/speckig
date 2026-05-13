# 0006 — Specs für app/user/actions/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `app/user/actions/LoginAction.php.spec` ← execute(username, password): Login + Session.
- `app/user/actions/CreateUserAction.php.spec` ← execute(...): Neuen User anlegen.
- Beide Specs nennen die wichtigen `conditions` (Input-Checks, Fehlerfälle wie BadCredentialsError, IdNotFoundError, UserInputError).
