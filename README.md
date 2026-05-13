# speckig

A spec layer that lives next to your code. `User.spec` ↔ `User.php`.
Edit specs like code, diff them like code, query them via tool.

See `Value.spec` for the problem this solves.
See `pm/how-to/` for conventions.

## Run

`./scripts/run.sh` — startet `php -S localhost:8083 -t app` und öffnet den Default-Browser.

Manuell ohne Browser: `php -S 127.0.0.1:8080 -t app`

## Entschleunigung — the core principle

> Wer weit gehen will, geht einen Blick zur Zeit.

Break every plan, ticket, spec, and commit down until each chunk is
**intuitive at a glance** for a human reader.

- A 300-line plan is worthless. Nobody reads it.
- A 5-line plan-step is gold. Everyone reads it.
- Many small steps take longer up front. They keep the project legible
  long after the original author is gone.
- This applies to LLM output too: if the bot writes more than fits on
  one screen, you stopped being able to verify it. Split.

If a chunk needs prose to explain, the chunk is too big.
