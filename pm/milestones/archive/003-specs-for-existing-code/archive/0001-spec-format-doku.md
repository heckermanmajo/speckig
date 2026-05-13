# 0001 — Spec-Format-Doku in pm/how-to/

See: pm/decisions/0005-spec-format.md

Bevor wir 30 Specs schreiben, brauchen wir die Konvention in `pm/how-to/` mit einem konkreten Beispiel, damit künftige Sessions (und der Bot) das Format einheitlich anwenden.

## Done when
- `pm/how-to/spec.md` existiert.
- Erklärt das YAML-Schema mit echtem Mini-Beispiel (z.B. `app/_share/DataClass.php` als Vorlage — ist kürzeste Datei).
- Listet Regeln aus Decision 0005 (eine Sprache pro Datei, Vendor skip, Felder weglassen wenn leer).
- `pm/how-to/README.md` listet `spec.md` in der Übersicht.
- Knapp halten, ein Bildschirm reicht.

## Done

- `pm/how-to/spec.md` angelegt (64 Zeilen, ein Bildschirm): YAML-Schema,
  DataClass-Mini-Beispiel, Regeln aus Decision 0005, "Was eine gute Spec
  ausmacht".
- `pm/how-to/README.md` listet `spec.md` jetzt nach `code_style.md`.
- M003-Status in `milestone.md` von `planned` auf `active` gesetzt,
  Ticket-Box `0001` abgehakt und Pfad auf `archive/` aktualisiert.
- **Filename-Konvention gewählt: `Foo.spec`** (PHP-Endung *nicht* im
  Spec-Namen). Begründung: Decision 0005 schreibt explizit
  `Foo.spec ↔ Foo.php` vor, ebenso `Value.spec` und das Projekt-`README.md`
  (`User.spec ↔ User.php`). Die Decision ist verbindlich; eine Abweichung
  würde 0005 supersedieren — das ist nicht Auftrag dieses Tickets.
- YAML-Snippets validiert mit `python3 -c "import yaml; yaml.safe_load(...)"`
  (PyYAML 6.0.1): sowohl das Schema-Beispiel als auch das DataClass-Beispiel
  parsen sauber.
- Folge-Frage (optional, kein Block): falls später JS dazukommt und es
  `Foo.php` neben `Foo.js` gibt, kollidieren beide auf `Foo.spec`. Dann
  eine Supersede-Decision für 0005 prüfen (z.B. `Foo.<ext>.spec`). Aktuell
  kein Problem, da Specs nur für PHP-Code gedacht sind.
