# commit

## Shape

```
[<prefix>] short imperative summary

Optional body: why, not what. One paragraph max.
```

The prefix says which kind of ticket this commit belongs to:

| Prefix          | When                                    | Example                          |
|-----------------|-----------------------------------------|----------------------------------|
| `[MMM/NNNN]`    | milestone ticket — `MMM` milestone num, `NNNN` ticket num inside it | `[001/0006] add tree view` |
| `[bug/NNNN]`    | bug fix — `NNNN` is the global bug num  | `[bug/0001] fix parsedown 8.4 deprecations` |
| `[chore]`       | trivial work or convention change, no ticket file | `[chore] tighten .gitignore`     |

Other rules:
- Summary in imperative mood: "add", "fix", "rename" — not "added".
- ≤ 72 chars for the summary line.

## Rules

- One ticket = one commit. If you need two commits, split the ticket.
- Spec changes and code changes go in the **same** commit.
- Move the ticket file from `open/` to `archive/` in the same commit.
- Append a `## Done` section to the ticket file in the same commit — bullet points of what was actually done. The archived ticket reads as a record after the fact, not a plan.
- If a decision was written for this commit, reference it in the ticket body: `See: pm/decisions/NNNN.md`.
- When the last ticket of a milestone is archived, `git mv pm/milestones/NNN-… pm/milestones/archive/NNN-…` in the same commit (see [[milestones]]).

## What does NOT need a ticket (use `[chore]`)
Typo fixes, formatting, trivial renames, doc reflows, convention changes in `pm/how-to/`.

## Forbidden
- Bundling unrelated changes.
- "WIP" commits on the main branch.
- Editing committed decisions — supersede instead (see [[decisions]]).
- Editing archived tickets.
