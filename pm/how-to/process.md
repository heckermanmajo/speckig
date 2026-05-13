# Process

## Loop
1. Pick or write a ticket in the milestone's `open/`.
2. If non-trivial: write a decision in `pm/decisions/` first.
3. Edit/create the `.spec` next to the code.
4. Implement.
5. Test.
6. Append a `## Done` section to the ticket file — terse bullet points of what was actually done (files touched, decisions made, deviations from the original plan). One screenful max.
7. Move ticket from the milestone's `open/` to its `archive/`.
8. Commit: **one ticket = exactly one commit**. Ticket move, `Done`-Section, code, spec — all in the same commit.

## File conventions
- `Foo.spec` describes `Foo.*` in the same directory.
- Milestones: `pm/milestones/NNN-kebab-title/` (3-digit, global).
- Tickets: `pm/milestones/NNN-…/{open,archive}/NNNN-kebab-title.md` (4-digit, restarts per milestone).
- Decisions: `pm/decisions/NNNN-kebab-title.md` (4-digit, global).

## See also
- Commit rules: [[commit]]
- Decision logging: [[decisions]]
- Milestone shape: [[milestones]]

## What does NOT need a ticket
Typo fixes, formatting, trivial renames. Everything else does.
