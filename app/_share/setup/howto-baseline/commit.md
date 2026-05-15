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

## Three-commit ticket lifecycle

A ticket is created and closed across **three commits**, not one. Each commit
captures a distinct phase of thinking. See [[process]] for the loop, and
[[milestones]] / [[bugs]] for the ticket shape.

```
Commit 1: [MMM/NNNN] open <title>
            └ Goal, Notes, Done when, Out of scope. No Plan yet.
Commit 2: [MMM/NNNN] plan <title>
            └ adds the ## Plan section.
Commit 3: [MMM/NNNN] <imperative summary>
            └ code + spec + ## Done + git mv open/ → archive/
```

Same shape for bugs, with prefix `[bug/NNNN]`.

Why three commits: each phase answers a different question ("should we?",
"how?", "did we?"). Bundling them collapses the questions and the goal
silently warps to fit the first plausible recipe. Splitting them costs
nothing and makes the thinking visible in `git log`.

## Rules

- One ticket = three commits (open / plan / close). Don't bundle phases.
- Within each phase, all related changes go in **one** commit.
- Spec changes and code changes go in the **same** commit (the close commit).
- The close commit moves the ticket from `open/` to `archive/` and appends `## Done`. The archived ticket reads as a record after the fact, not a plan.
- If a decision was written for this ticket, reference it in the ticket body: `See: pm/decisions/NNNN.md`. The decision is its own commit, before Commit 1.
- When the last ticket of a milestone is archived, `git mv pm/milestones/NNN-… pm/milestones/archive/NNN-…` in the same close commit (see [[milestones]]).

## What does NOT need a ticket (use `[chore]`)
Typo fixes, formatting, trivial renames, doc reflows, convention changes in `pm/how-to/`. A `[chore]` is a single standalone commit — no open/plan/close cycle.

## Forbidden
- Bundling unrelated changes.
- Bundling phases (open+plan, or plan+close) into one commit.
- "WIP" commits on the main branch.
- Editing committed decisions — supersede instead (see [[decisions]]).
- Editing archived tickets.
