# terms

Project-specific vocabulary lives in `pm/terms/`. One term per file: `pm/terms/foo.md`.

## When to add a term
- A word means something different here than in general usage.
- A word is ambiguous and the project has picked one meaning.
- A new concept needs a name before code/spec/ticket can refer to it.

## File shape
```
# <Term>

One-line definition.

See: [[other-term]], related.spec, pm/milestones/NNN-…/open/NNNN.md.
```

## Rules
- One file per term. Filename = lowercase kebab.
- Definition first, context after. No prose intro.
- If two terms overlap, pick one and mark the other as alias: `Alias of: [[canonical]]`.
- Specs, tickets, decisions may link to terms by filename.
