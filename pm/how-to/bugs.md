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
```
# NNNN — <Title>

Symptom: one or two lines describing what is broken and how it shows up.
See: pm/decisions/NNNN-foo.md       # optional, if relevant

## Done when
- Acceptance criterion, one bullet.
- Another acceptance criterion.

## Done                              # appended when archived
- What was actually done, terse bullets.
- Root cause, fix, verification.
```

## Rules
- Filenames: `NNNN-kebab-title.md`, 4-digit zero-padded. Numbering is
  **global to bugs** (separate from milestone ticket numbers and decisions).
- One bug = one commit. Same workflow as milestone tickets: append `## Done`,
  `git mv open/ → archive/`, commit — all in one go.
- Commit prefix: `[bug/NNNN]`. Example: `[bug/0001] fix parsedown 8.4 deprecations`.

## See also
- [[process]] — the dev loop.
- [[commit]] — commit message shape.
- [[milestones]] — for non-bug work.
