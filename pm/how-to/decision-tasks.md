# decision-tasks

Lives in `pm/decision-tasks/`. One file per open decision the human owes.
The bot writes these when it hits a choice it should not make alone.

## When the bot writes one
- A non-obvious trade-off appears mid-work.
- Multiple plausible paths exist and the bot lacks context to pick.
- A decision needs human authority (scope, naming, policy, cost).

## File shape
```
# DT-NNNN — <Title>

## Question
One sentence. The actual choice.

## Options
- A — one line.
- B — one line.
- C — one line.

## Context
2-4 bullets. What forced the question, what the bot has tried.

## Bot recommendation
One line. Optional.
```

## Lifecycle
- **Open**: bot drops a file in `pm/decision-tasks/`.
- **Resolved**: human picks an option, writes the decision into
  `pm/decisions/` (one sentence), then deletes the decision-task file.
- **Dropped**: human deletes the file. Git keeps history.

## Rules
- One question per file. No stacking.
- Numbering `DT-NNNN`, global, never reused.
- Bot may recommend, never resolve its own decision-task.
- Filename: `NNNN-kebab-title.md`.
