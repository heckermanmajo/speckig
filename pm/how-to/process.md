# Process

## Where work lives

Two kinds of tickets, two homes:

- **Milestone ticket** — part of a named outcome. Lives in
  `pm/milestones/NNN-title/open/NNNN-foo.md`.
- **Bug** — fixes something broken, no milestone. Lives in
  `pm/bugs/open/NNNN-foo.md`. See [[bugs]].

If neither fits, it's probably not a ticket. Trivial work (typo fix,
formatting, rename) goes in a `[chore]` commit without a ticket file.

## Loop

1. Pick or write a ticket in the right `open/` folder.
2. If non-trivial: write a decision in `pm/decisions/` first.
3. Edit/create the `.spec` next to the code.
4. Implement.
5. Test.
6. Append a `## Done` section to the ticket file — terse bullet points of what was actually done (files touched, decisions made, deviations from the original plan). One screenful max.
7. `git mv` the ticket from `open/` to `archive/` (same parent folder).
8. If this ticket completed a milestone (all boxes ticked, `Status: done`), also `git mv pm/milestones/NNN-… pm/milestones/archive/NNN-…` in the same commit.
9. Commit: **one ticket = exactly one commit**. Ticket move, `Done`-Section, code, spec — all together.

## File conventions

- `Foo.spec` describes `Foo.*` in the same directory.
- Milestones (active): `pm/milestones/NNN-kebab-title/` (3-digit, global).
- Milestones (done): `pm/milestones/archive/NNN-kebab-title/` (same shape, just relocated).
- Milestone tickets: `pm/milestones/NNN-…/{open,archive}/NNNN-kebab-title.md` (4-digit, restarts per milestone).
- Bugs: `pm/bugs/{open,archive}/NNNN-kebab-title.md` (4-digit, global to bugs).
- Decisions: `pm/decisions/NNNN-kebab-title.md` (4-digit, global, append-only — see [[decisions]]).

## See also
- Commit rules: [[commit]]
- Decision logging: [[decisions]]
- Milestone shape: [[milestones]]
- Bugs: [[bugs]]

## What does NOT need a ticket
Typo fixes, formatting, trivial renames, doc reflows. Use `[chore]` commit.
