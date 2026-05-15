# @bot markers

Anywhere in the codebase (code, spec, ticket, decision) you can write `@bot ...`
to leave a task or question for the AI agent.

## Examples
```
// @bot rename this to handle_login, callers in auth/
# @bot is this still needed after we dropped sessions v1?
```

## Bot behavior
On request ("handle @bots"), the bot:
1. Greps the repo for `@bot` markers.
2. For each one, classifies:
   - **trivial** → fix inline, remove the marker, mention in commit.
   - **non-trivial** → ask the user: "make a ticket for this?"
3. Never silently deletes a marker without acting on it.

## Rules
- One `@bot` line = one concern. Don't stack.
- Keep the note short. If it needs prose, it needs a ticket.
- The marker disappears when the concern is resolved.
