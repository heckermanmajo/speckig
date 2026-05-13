# 0005 — Spec-Format

- Eine `.spec`-Datei liegt direkt neben jeder Code-Datei: `Foo.spec` ↔ `Foo.php`.
- Spec-Inhalt ist YAML, flach pro Funktion — kein verschachteltes args/returns/throws-Schema.
- Schema pro Datei (Mindestfelder `file`, `purpose`, `functions`):

```yaml
file: Foo.php
purpose: Ein Satz, was die Datei tut.
functions:
  - name: foo_bar
    does: Ein Satz, was die Funktion tut.
    conditions:
      - "wichtige Bedingung, Invariante oder Fehlerfall"
      - "noch eine, optional"
```

- Felder dürfen weggelassen werden, wenn sie nichts tragen — `conditions: []` weglassen statt leer schreiben.
- Spec-Sprache: Englisch oder Deutsch, eine Sprache pro Datei, kurz halten.
- Vendor-Code (`app/_share/vendor/`) bekommt keine Spec.
- Drift wird nicht automatisch geprüft, nur gezeigt — entspricht Value.spec (`Drift is shown, not prevented`).
