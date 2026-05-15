# milestones

A milestone groups tickets toward a named outcome. Lives in `pm/milestones/`.
One **folder** per milestone: `pm/milestones/NNN-kebab-title/`. When the
milestone is `done`, the whole folder moves under `pm/milestones/archive/`.

```
pm/milestones/
  NNN-kebab-title/         # active or planned milestones live here
    milestone.md
    open/                  # NNNN-kebab-title.md (ticket numbers restart per milestone)
    archive/
  archive/
    NNN-kebab-title/       # done milestones — same internal shape, moved as-is
```

## When to add a milestone
- You need to plan more than 2-3 tickets ahead.
- A goal needs a name so tickets can point to it.
- You want to co-plan with an LLM and need a shared anchor.

If you only have one ticket in mind, skip the milestone — but most non-milestone work is a **bug**, which has its own home in `pm/bugs/` (see [[bugs]]). Bugs do not need a milestone.

## milestone.md shape
```
# NNN — <Title>

Goal: one sentence. What's true when this is done.
Status: planned | active | done | dropped

## Tickets
- [ ] open/NNNN-foo.md
- [x] archive/NNNN-bar.md

## Out of scope
- Things explicitly NOT in this milestone.
```

## Rules
- Max 5 lines per planning step. If a step needs more, split it into its own milestone.
- A ticket belongs to exactly one milestone (its folder).
- Ticket numbers restart at `0001` inside each milestone.
- Tick the box when the ticket moves into `archive/`.
- `Status: done` only when all boxes are ticked.
- When `Status: done` is set, move the whole milestone folder via `git mv pm/milestones/NNN-… pm/milestones/archive/NNN-…` in the same commit that flips the status.

## Ticket shape

A ticket has three layers, written in this order:

1. **Goal** — *what* should be true at the end, in user/business terms. One sentence. No file paths, no function names.
2. **Notes** — *what to watch out for*: constraints, edge cases, risks, touchpoints, out-of-scope. Still no concrete technical recipe.
3. **Plan** — *how* to do it technically: files, functions, endpoints, selectors, exact steps. This is the implementation recipe.

The split matters. The Goal answers "should we even do this?". The Notes answer "what could go wrong?". The Plan answers "what do I type?". Mixing them is the default failure mode — once function names land in `Done when`, the goal disappears under the recipe and reviewers can no longer separate "is this the right thing" from "is this the right way".

```
# NNNN — <Title>

See: pm/decisions/NNNN-foo.md       # optional
Blocked by: NNNN, NNNN               # optional

## Goal
One sentence. What is true in the world when this ticket is done.

## Notes
- Constraints, edge cases, risks, touchpoints.
- Things easy to break in passing.
- Things that look like they belong here but don't.

## Done when
- Acceptance criterion, one bullet. Observable from outside the code.
- Another acceptance criterion.

## Out of scope
- Things explicitly NOT in this ticket.

## Plan                              # added in a second commit, see below
- Step-by-step technical plan. Files to touch, functions to add, endpoints to call.
- May be proposed/extended by the implementing subagent before work starts.

## Done                              # appended when archived
- What was actually done, terse bullets.
- Files touched, decisions made, deviations from the plan.
```

## Two-step ticket creation

Tickets are opened in **two separate commits**:

1. **`[MMM/NNNN] open <title>`** — creates the ticket file with `Goal`, `Notes`, `Done when`, `Out of scope`. No `Plan` yet. This is the "should we even do this, and what does done look like" commit.
2. **`[MMM/NNNN] plan <title>`** — appends the `## Plan` section. This is the "how do we actually do it" commit.

Why two commits: it forces a thinking pause between "what" and "how". If you write the plan in the same breath as the goal, the goal silently warps to fit whatever recipe came to mind first. Two commits give the goal a moment to stand on its own.

The `Plan` section may be written by the **main session** (when planning ahead) or **proposed/extended by the implementing subagent** before it starts coding. If a subagent extends the plan, that still gets its own `plan` commit — not bundled into the implementation commit.

The implementation itself is then a **third commit** (`[MMM/NNNN] <imperative summary>`) that contains code + spec + `## Done` + the `git mv` to `archive/`. See [[commit]].

## See also
- Commit shape and the three-commit lifecycle: [[commit]]
- Bugs follow the same Goal/Notes/Done-when/Plan structure: [[bugs]]
- Entschleunigung: top-level [[README]] — break work down until each chunk is intuitive at a glance.
