# decisions

Lives in `pm/decisions/`. Append-only. If it's in the repo, it's accepted.
Date and history come from git.

## Shape
One file may hold many decisions. Each decision = one sentence.

```
# NNNN — <Title>

- Decision one, one line.
- Decision two, one line.
- Decision three, one line.
```

## Rules
- One sentence per decision. If it needs more, it's a report (`pm/reports/`) or a ticket.
- Group related decisions in one file.
- Numbering is global, never reused.
- Never edit old decisions. Supersede by writing a new file that says so.

## When to log
- A non-obvious choice was made.
- A choice closes off alternatives someone might re-open later.
- A bot made a judgment call you want trackable.

Trivial choices (variable names, formatting) do not need a decision.
