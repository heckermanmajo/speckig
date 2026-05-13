# spec

Was eine `.spec`-Datei ist und wie sie aussieht. Schreibt
[[decisions/0005-spec-format]] aus.

## Wo Specs liegen

Direkt neben der Code-Datei, gleicher Stamm, Endung `.spec`:
`app/_share/DataClass.php` bekommt `app/_share/DataClass.spec`. Die
PHP-Endung kommt **nicht** in den Spec-Namen — entspricht dem Beispiel
`User.spec ↔ User.php` aus `Value.spec` und Decision 0005.

`Value.spec` im Projekt-Root ist die einzige Ausnahme: konzeptionell,
keiner Code-Datei zugeordnet.

## Schema

```yaml
file: Foo.php
purpose: Ein Satz, was die Datei tut.
functions:
  - name: foo_bar
    does: Ein Satz, was die Funktion tut.
    conditions:
      - "wichtige Bedingung, Invariante oder Fehlerfall"
```

Mindestfelder: `file`, `purpose`. `functions` weglassen, wenn die Datei
nur Daten/Klassenfelder enthält. `conditions` weglassen, wenn leer —
nicht `conditions: []` schreiben.

## Mini-Beispiel: DataClass

`app/_share/DataClass.php` ist eine reine Datenklasse mit `$id` und
`$created_at`, keine Methoden. Spec daneben in `app/_share/DataClass.spec`:

```yaml
file: DataClass.php
purpose: Basisklasse für DB-Entities mit id und created_at.
```

Das reicht. Kein `functions:`-Block nötig.

## Regeln

- Felder weglassen, wenn sie nichts tragen (statt leere Liste).
- Eine Sprache pro Datei — Englisch oder Deutsch, konsistent.
- Vendor-Code unter `app/_share/vendor/` bekommt **keine** Spec.
- Drift wird gezeigt, nicht erzwungen (`Value.spec`: *Drift is shown,
  not prevented*).

## Was eine gute Spec ausmacht

- Kurz. Pseudocode-Stil, nicht Prosa.
- `does:` ist ein Satz, nicht eine Erklärung.
- `conditions:` listet das Nicht-Triviale: Auth-Checks, Throw-Fälle,
  Invarianten. Nicht "returns string".
- Wenn eine Funktion keinen Ein-Satz-`does:` trägt, ist die Funktion zu
  komplex — Hinweis fürs Refactor, nicht zum Roman-Schreiben in der Spec.

## See also

- [[decisions/0005-spec-format]]
- [[process]]
