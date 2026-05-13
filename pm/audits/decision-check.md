# Audit: decision-check

## Goal
Verify decisions in `pm/decisions/` against the actual codebase and specs.

## Input
- One decision file (`pm/decisions/NNNN-*.md`), or all of them.

## Steps
1. For each decision bullet:
   - Locate where it should be visible (code, spec, config, dep).
   - Check it is actually there.
   - Check no code/spec contradicts it.
2. Classify each bullet:
   - **holds** — still true in the codebase.
   - **violated** — code/spec contradicts the decision.
   - **redundant** — already enforced by code/spec/lint/test; the
     written decision adds nothing. Candidate for removal.
   - **stale** — refers to things that no longer exist.

## Output
Write to `pm/reports/NNNN-decision-check-<scope>.md`.

Per decision:
- decision file + bullet (quote one line).
- classification.
- evidence (file:line or "not found").
- recommendation: keep / fix code / remove decision / supersede.

## Rules
- A decision that is fully redundant (covered by code, spec, test, or
  lint config) should be removed. The code is the source of truth.
- A decision that is violated triggers either a code fix (ticket) or a
  supersede (new decision file).
- Never auto-edit decisions. Audit only reports.
