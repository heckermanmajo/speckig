# 0006 — Specs für app/user/actions/

See: pm/decisions/0005-spec-format.md
Blocked by: 0001

## Done when
- `app/user/actions/LoginAction.spec` ← execute(username, password): Login + Session.
- `app/user/actions/CreateUserAction.spec` ← execute(...): Neuen User anlegen.
- Beide Specs nennen die wichtigen `conditions` (Input-Checks, Fehlerfälle wie BadCredentialsError, IdNotFoundError, UserInputError).

## Done
- Added `app/user/actions/LoginAction.spec` — 1 function (`execute`), 5 conditions (UserInputError x2, IdNotFoundError, BadCredentialsError, perform_login note).
- Added `app/user/actions/CreateUserAction.spec` — 1 function (`execute`), 10 conditions (admin gate, 6 UserInputError validations, BadStateError, password-hash invariant, is_admin mapping).
- Grep over both PHP files matches every `throws ...` condition to a real `throw new` line (LoginAction lines 25/26/36/44; CreateUserAction lines 39/44/53/60/69/83/102; admin gate via `app::enforce_plattform_admin`).
- `python3 -c "import yaml; yaml.safe_load(...)"` passes for both specs.
- No PHP touched.
