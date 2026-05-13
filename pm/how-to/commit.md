# commit

## Shape
```
[MMM/NNNN] short imperative summary

Optional body: why, not what. One paragraph max.
```

- `MMM` = milestone number, `NNNN` = ticket number inside that milestone.
- Summary in imperative mood: "add", "fix", "rename" — not "added".
- ≤ 72 chars for the summary line.

## Rules
- One ticket = one commit. If you need two commits, split the ticket.
- Spec changes and code changes go in the **same** commit.
- Move the ticket file from the milestone's `open/` to its `archive/` in the same commit.
- Append a `## Done` section to the ticket file in the same commit — bullet points of what was actually done. The archived ticket reads as a record after the fact, not a plan.
- If a decision was written for this commit, reference it: `See: pm/decisions/NNNN.md`.

## What does NOT need a ticket (and may commit without `[MMM/NNNN]`)
Typo fixes, formatting, trivial renames. Prefix with `[chore]` instead.

## Forbidden
- Bundling unrelated changes.
- "WIP" commits on the main branch.
- Editing committed decisions or archived tickets.
