# bugs

Bugs are tickets that fix something broken. They do **not** belong to a
milestone — milestones group new outcomes, bugs interrupt them.

```
pm/bugs/
  open/        # NNNN-kebab-title.md (global numbering, restarts nowhere)
  archive/
```

## When something is a bug (not a milestone ticket)

- A working thing stopped working.
- A deprecation/warning appeared.
- A security issue was found.
- A regression slipped through.

If you're adding a new feature, it's a milestone ticket, not a bug.

## Bug file shape

Bugs use the same three-layer structure as milestone tickets (see
[[milestones]]). The only addition is the `Symptom` field at the top, which
captures what's observably broken — that's the bug-specific framing for *why*
the ticket exists in the first place.

```
# NNNN — <Title>

Symptom: one or two lines describing what is broken and how it shows up.
See: pm/decisions/NNNN-foo.md       # optional, if relevant

## Goal
One sentence. What is true in the world when this bug is fixed.

## Notes
- Suspected root cause, reproduction conditions.
- Constraints (e.g. don't bump the dep, keep behaviour for callers X/Y).
- Touchpoints — code that looks related but isn't.

## Done when
- Acceptance criterion. Observable from outside the code (the broken thing now works).
- Regression check, if applicable.

## Out of scope
- Adjacent issues that surfaced but belong in their own bug ticket.

## Plan                              # added in a second commit
- Step-by-step technical plan. Files to touch, root-cause fix vs. workaround, verification commands.
- May be proposed/extended by the implementing subagent before work starts.

## Done                              # appended when archived
- What was actually done, terse bullets.
- Root cause, fix, verification.
```

## Two-step ticket creation

Same rule as milestone tickets: bugs are opened in **two separate commits**
before the implementation commit.

1. **`[bug/NNNN] open <title>`** — `Symptom`, `Goal`, `Notes`, `Done when`, `Out of scope`. No `Plan` yet.
2. **`[bug/NNNN] plan <title>`** — appends `## Plan`.
3. **`[bug/NNNN] <imperative summary>`** — implementation + `## Done` + `git mv open/ → archive/`.

The thinking-pause between "what is broken / what does fixed look like" and
"how do I fix it" matters for bugs too — maybe more, because bug pressure
makes it tempting to jump straight to the first plausible fix.

## Rules
- Filenames: `NNNN-kebab-title.md`, 4-digit zero-padded. Numbering is
  **global to bugs** (separate from milestone ticket numbers and decisions).
- Commit prefix: `[bug/NNNN]`. Example: `[bug/0001] fix parsedown 8.4 deprecations`.

## See also
- [[process]] — the dev loop.
- [[commit]] — commit message shape and the three-commit lifecycle.
- [[milestones]] — ticket shape is shared; non-bug work lives there.
