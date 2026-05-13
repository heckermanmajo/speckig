# reports

Research reports, audits, comparisons. Lives in `pm/reports/`.
One file per report: `NNNN-kebab-title.md`.

## When to write a report
- Web research on a topic (landscape, competitors, prior art).
- Audit results (security, code-quality, deps).
- Comparison of options before a decision.

A report informs decisions. The decision itself goes in `pm/decisions/`.

## File shape
```
# NNNN — <Title>

Date: YYYY-MM-DD
Type: research | audit | comparison
Status: draft | final

## TL;DR
3-5 bullets. The whole point of the report.

## Findings
Bullet-heavy. Sources inline as URLs.

## Sources
- url — what it is.

## Hooks for us
What we should adopt / explore / ignore.
```

## Rules
- TL;DR comes first. If a reader stops there, they got 80%.
- Numbering is global, never reused.
- Bullets over prose.
- Link to tickets, decisions, ideas that the report informs.
