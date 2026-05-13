# ideas

Pre-stage before tickets. Lives in `pm/ideas/`. One file per idea: `pm/ideas/<slug>.md`.

## Purpose
Humans (and bots) dump rough thoughts here without committing to building them.
An idea may become a ticket, may be merged with another, or may die.

## File shape
```
# <Title>

One-line essence.

Notes, sketches, open questions. Whatever.
```

No required structure. No frontmatter. Length: short.

## Lifecycle
- **Add**: drop a file. No review needed.
- **Promote**: move/rewrite into a ticket in the relevant milestone's `open/`. Delete or keep the idea file as historical context.
- **Drop**: delete the file. Git history keeps it.

## Rules
- Filename = lowercase kebab, no numbering.
- Ideas are cheap. Don't gatekeep them.
- An idea is not a commitment. A ticket is.
