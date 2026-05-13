# audits

Audit definitions live in `pm/audits/`. One file per audit type: `<name>.md`.
Audit results are reports, written to `pm/reports/`.

## What an audit is
A repeatable check the bot runs against the repo. Defined by a markdown
file that says: goal, input, steps, output, rules.

## File shape
```
# Audit: <name>

## Goal
One sentence.

## Input
What the audit operates on (files, scope).

## Steps
Numbered list, terse.

## Output
Where the report goes, what shape.

## Rules
Constraints, what the audit must/must not do.
```

## Rules
- Audits never auto-edit. They report.
- An audit run produces one report file in `pm/reports/`.
- Audits may suggest tickets or decisions, never create them silently.

## Existing audits
- `decision-check.md` — verify `pm/decisions/` against code & specs.
