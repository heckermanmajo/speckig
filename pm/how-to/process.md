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

A ticket lives in three phases, each a separate commit:

### Phase 1 — Open (the *what*)
1. Pick or write a ticket in the right `open/` folder.
2. Fill in `Goal`, `Notes`, `Done when`, `Out of scope`. **No `Plan` yet.**
3. If non-trivial: write a decision in `pm/decisions/` first.
4. Commit: `[MMM/NNNN] open <title>` or `[bug/NNNN] open <title>`.

The point of stopping here is to let the goal stand on its own before any
technical recipe colours it.

### Phase 2 — Plan (the *how*)
5. Append a `## Plan` section to the ticket file: step-by-step technical
   plan, files to touch, functions to add, endpoints, verification commands.
6. May be written by the main session ahead of time, or by the
   implementing subagent immediately before it starts coding.
7. Commit: `[MMM/NNNN] plan <title>` or `[bug/NNNN] plan <title>`.

### Phase 3 — Close (the *do*)
8. Edit/create the `.spec` next to the code.
9. Implement.
10. Test.
11. Append a `## Done` section to the ticket file — terse bullets of what was actually done (files touched, decisions made, deviations from the plan). One screenful max.
12. `git mv` the ticket from `open/` to `archive/` (same parent folder).
13. If this ticket completed a milestone (all boxes ticked, `Status: done`), also `git mv pm/milestones/NNN-… pm/milestones/archive/NNN-…` in the same commit.
14. Commit: `[MMM/NNNN] <imperative summary>`. Code, spec, `Done`-section, ticket move — all in this one commit.

## File conventions

- `Foo.spec` describes `Foo.*` in the same directory.
- Milestones (active): `pm/milestones/NNN-kebab-title/` (3-digit, global).
- Milestones (done): `pm/milestones/archive/NNN-kebab-title/` (same shape, just relocated).
- Milestone tickets: `pm/milestones/NNN-…/{open,archive}/NNNN-kebab-title.md` (4-digit, restarts per milestone).
- Bugs: `pm/bugs/{open,archive}/NNNN-kebab-title.md` (4-digit, global to bugs).
- Decisions: `pm/decisions/NNNN-kebab-title.md` (4-digit, global, append-only — see [[decisions]]).

## See also
- Ticket shape (Goal / Notes / Done when / Plan / Done): [[milestones]]
- Same shape for bugs: [[bugs]]
- Commit rules and the three-commit lifecycle: [[commit]]
- Decision logging: [[decisions]]

## What does NOT need a ticket
Typo fixes, formatting, trivial renames, doc reflows. Use `[chore]` commit.
