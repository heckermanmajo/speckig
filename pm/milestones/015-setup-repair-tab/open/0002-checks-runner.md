# 0002 — Checks-Runner

## Goal
Es gibt eine zentrale Stelle, die eine Liste von Checks ausfuehrt und pro
Check ein normalisiertes Ergebnis zurueckliefert. Die Setup-Seite rendert
diese Ergebnisse als Tabelle/Liste mit Status-Indikatoren.

## Notes
- Jeder Check liefert ein **gleiches Schema**: `{name, status, hint,
  can_repair, repair_action}`. Damit das UI keinen Sonderfall pro Check
  bauen muss.
- `status` ist eines aus `ok | warn | fail` — kein numerischer Score.
- `can_repair` ist nur dann `true`, wenn `repair_action` einen
  Identifier hat, den der Repair-Endpoint (0004) versteht.
- Runner darf einzelne Check-Exceptions nicht den ganzen Lauf
  abbrechen — Crash in einem Check → Eintrag `status: fail, hint:
  <message>`, naechster Check laeuft.
- Checks selbst kommen erst in 0003 — hier wird nur das Skelett +
  ein/zwei Beispiel-Checks gebaut, um das Rendering zu zeigen.

## Done when
- Setup-Seite zeigt eine Liste von Check-Ergebnissen mit Name, Status,
  Hint, ggf. Repair-Button.
- Mindestens zwei Beispiel-Checks laufen (z.B. "PHP-Version vorhanden",
  "SPECKIG_ROOT gesetzt") und werden angezeigt.
- Ein Check, der absichtlich eine Exception wirft, kippt den Lauf
  nicht — er erscheint mit Status `fail` und einem Hint, die anderen
  Checks laufen normal.
- Spec-Datei dokumentiert das Result-Schema.

## Out of scope
- Vollstaendige Check-Liste (0003).
- Funktionierende Repair-Buttons (0004).
- Periodisches Re-Run / Polling.

See: pm/how-to/code_style.md
