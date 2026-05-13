# 0001 — cursor:pointer + child-count

Klickbares soll auch klickbar aussehen. Ordner sollen ihre Größe in grau zeigen.

See: pm/decisions/0004-ux-policy.md

## Done when
- `cursor: pointer` ist via `<style>` für `summary` und für die Tree-Datei-`<a>`-Elemente gesetzt.
- `render_tree()` in `app/index.php` hängt an jeden Ordner-Summary die Anzahl der direkten Kinder in Klammern an: `decisions/ (5)`.
- Die Klammer-Zahl ist visuell grau (z.B. `<span style="color:#888">(5)</span>`).
- Versteckte Dateien (`.gitkeep`, `.git`, `.DS_Store`) werden nicht mitgezählt.
- Smoketest: `curl -s http://127.0.0.1:8080/` zeigt z.B. `decisions/ <span ...>(5)</span>`.

## Done
- `app/index.php`: `<style>`-Block angepasst — `nav summary` von `cursor: default` auf `cursor: pointer` geflippt, `nav a` um `cursor: pointer` ergaenzt.
- `app/index.php`: neue Helper-Funktion `count_visible_children(string $dir_abs): int` — scandir + Punkt-Prefix-Filter, gibt direkte Kinder-Anzahl (rekursionsfrei) zurueck.
- `app/index.php`: in `render_tree()` Ordner-Branche zaehlt direkte sichtbare Kinder und haengt ` <span style="color:#888">(N)</span>` an das `<summary>` an.
- Versteckte Files (`.gitkeep`, `.git`, `.DS_Store`, alles mit `.`-Prefix) werden nicht mitgezaehlt — gleiche Filter-Logik wie der bestehende `render_tree()`.
- Smoketest gegen `php -S 127.0.0.1:8080 -t app`: `grep "cursor: pointer" /tmp/speckig-root.html` trifft `nav summary { cursor: pointer; }` und `nav a { ... cursor: pointer; }`. `grep -oE "decisions/ <span[^>]*>\([0-9]+\)"` ergibt `decisions/ <span style="color:#888">(4)`, passend zu `ls pm/decisions/ | grep -v '^\.' | wc -l` = 4.
- Inline-Style (`color:#888`) bewusst gewaehlt — entspricht "kaum CSS" aus Decision 0002 / 0004.
