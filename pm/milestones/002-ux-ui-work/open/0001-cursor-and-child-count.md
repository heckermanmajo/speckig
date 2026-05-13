# 0001 — cursor:pointer + child-count

Klickbares soll auch klickbar aussehen. Ordner sollen ihre Größe in grau zeigen.

See: pm/decisions/0004-ux-policy.md

## Done when
- `cursor: pointer` ist via `<style>` für `summary` und für die Tree-Datei-`<a>`-Elemente gesetzt.
- `render_tree()` in `app/index.php` hängt an jeden Ordner-Summary die Anzahl der direkten Kinder in Klammern an: `decisions/ (5)`.
- Die Klammer-Zahl ist visuell grau (z.B. `<span style="color:#888">(5)</span>`).
- Versteckte Dateien (`.gitkeep`, `.git`, `.DS_Store`) werden nicht mitgezählt.
- Smoketest: `curl -s http://127.0.0.1:8080/` zeigt z.B. `decisions/ <span ...>(5)</span>`.
