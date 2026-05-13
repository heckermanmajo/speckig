# 0007 — Markdown-Render

See: pm/decisions/0002-php-infra.md
Blocked by: 0006

## Done when
- `app/_share/vendor/Parsedown.php` ist eingecheckt mit Originalheader.
- `app/view.php` rendert `.md`-Dateien aus `pm/` als HTML.
- Andere Endungen werden als `<pre>`-Plaintext gezeigt.
- Links zwischen Markdown-Files funktionieren relativ.
- Pfad-Traversal wird hart abgewiesen (gleicher Check wie in 0006).
