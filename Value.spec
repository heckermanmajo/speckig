# Value

## Pain
AI writes code faster than humans can read it. Commits grow large, intent is lost, control erodes.
Specs in separate docs rot. Code-only review forces you to reconstruct intent from syntax.

## Solution
A spec file lives next to every code file (`User.spec` ↔ `User.php`).
Specs are short: types, stubs, one-line intent. Editable like code, diffable like code.
Tickets and decisions live in the repo too.

## Promise
- You skim `*.spec` before reading code → faster mental parse.
- You edit the spec first → forces small, intentful changes.
- `git diff` on specs = a readable changelog of intent.
- LLMs query specs/tickets/decisions through one tool → no context loss between sessions.

## Not goals
- Generate code from spec.
- Enforce spec/code sync automatically. Drift is shown, not prevented.
