# milestones

A milestone groups tickets toward a named outcome. Lives in `pm/milestones/`.
One **folder** per milestone: `pm/milestones/NNN-kebab-title/`.

```
pm/milestones/NNN-kebab-title/
  milestone.md
  open/        # NNNN-kebab-title.md (ticket numbers restart per milestone)
  archive/
```

## When to add a milestone
- You need to plan more than 2-3 tickets ahead.
- A goal needs a name so tickets can point to it.
- You want to co-plan with an LLM and need a shared anchor.

If you only have one ticket in mind, skip the milestone. There is no global ticket pool — every ticket belongs to exactly one milestone.

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

## See also
- Entschleunigung: top-level [[README]] — break work down until each chunk is intuitive at a glance.
