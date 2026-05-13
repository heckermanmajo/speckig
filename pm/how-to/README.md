# how-to

General AI-coding & project-management conventions. Project-agnostic.

## pm/ folder map

```
pm/
  milestones/                 # active milestones, each its own folder
    NNN-title/
      milestone.md
      open/    archive/
    archive/                  # done milestones, moved as-is
      NNN-title/
  bugs/                       # bugs — separate from milestones
    open/    archive/
  decisions/                  # append-only — supersede, don't edit
  decision-tasks/             # open questions the bot raises to the human
  ideas/                      # rough thoughts, pre-ticket
  terms/                      # project-specific vocabulary
  reports/                    # research / audit writeups
  audits/                     # repeatable check definitions
  how-to/                     # the conventions you are reading now
```

Project-specific content also lives in `*.spec` files next to code.

## Conventions

- `process.md` — the dev loop, file conventions, start here.
- `milestones.md` — grouping tickets toward a named outcome.
- `bugs.md` — fixing broken things without a milestone.
- `commit.md` — commit message shape & rules.
- `code_style.md` — PHP style rules with examples from `app/_share/`.
- `decisions.md` — how to log decisions in `pm/decisions/` (append-only).
- `decision-tasks.md` — open questions the bot escalates to the human.
- `audits.md` — repeatable checks defined in `pm/audits/`.
- `reports.md` — research/audit reports in `pm/reports/`.
- `at-bot.md` — `@bot` markers in code and how the bot handles them.
- `terms.md` — when and how to define project-specific vocabulary in `pm/terms/`.
- `ideas.md` — pre-stage before tickets, lives in `pm/ideas/`.
- `brainstorm.md` — *(empty, fill on demand)*
- `testing.md` — *(empty, fill on demand)*
