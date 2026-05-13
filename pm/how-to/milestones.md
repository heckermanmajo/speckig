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
```
# NNNN — <Title>

See: pm/decisions/NNNN-foo.md       # optional
Blocked by: NNNN, NNNN               # optional

## Done when
- Acceptance criterion, one bullet.
- Another acceptance criterion.

## Done                              # appended when archived
- What was actually done, terse bullets.
- Files touched, decisions made, deviations from the plan.
```

The `## Done when` section is written before work starts. The `## Done` section is appended in the same commit that moves the ticket to `archive/`.

## See also
- Entschleunigung: top-level [[README]] — break work down until each chunk is intuitive at a glance.
