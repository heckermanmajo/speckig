# 0005 — How-to-Baseline als versioniertes Asset

## Goal
Es gibt einen versionierten "Referenz-Stand" der `pm/how-to/`-Files im
Repo, gegen den die Setup-Checks vergleichen koennen und aus dem
Repair-Aktionen fehlende How-tos wiederherstellen.

## Notes
- Naheliegender Ort: `app/_share/setup/howto-baseline/<name>.md` —
  separater Pfad, damit klar ist: das ist eine Snapshot-Kopie, kein
  Live-Doc.
- Wie der Stand **fortgeschrieben** wird, ist eine Decision wert
  (siehe Out-of-scope: Decision schreiben, nicht im Ticket
  improvisieren). Vorschlag fuer die Decision: Baseline wird per Hand
  aktualisiert, immer wenn eine how-to-Konvention sich aendert; ein
  Setup-Check meldet "Drift" als `warn` ohne automatischen Repair.
- Hash-Vergleich: SHA-256 ueber den Dateiinhalt. Setup-Check sagt
  `ok` bei Gleichheit, `warn` bei Drift, `fail` bei fehlender
  Live-Datei.
- Repair-Action `restore_how_to:<name>` kopiert das Baseline-File auf
  den Live-Pfad — **nur** bei fehlender Live-Datei. Bei Drift kein
  ueberschreiben (das ist Userin-Entscheidung).
- Baseline-Bundle nicht in die Save-/Edit-Whitelists aufnehmen — es
  ist ein Asset, kein editierbares Doc.

## Done when
- Verzeichnis `app/_share/setup/howto-baseline/` existiert und enthaelt
  Kopien jedes aktuellen `pm/how-to/*.md`.
- Setup-Tab zeigt pro how-to-File: vorhanden / Drift / fehlend.
- Repair-Action `restore_how_to:bugs.md` legt `pm/how-to/bugs.md`
  wieder an, wenn man es vorher testweise verschiebt.
- Drift-Fall (Live-Datei manuell veraendert) zeigt `warn` und bietet
  **keinen** Repair-Button.
- Eine Decision-Datei in `pm/decisions/` dokumentiert die
  Fortschreibungs-Regel der Baseline.

## Out of scope
- Auto-Sync zwischen Live und Baseline.
- Diff-Anzeige zwischen Live und Baseline (kann ein spaeteres Ticket
  werden).
- Baseline fuer andere Verzeichnisse als `pm/how-to/`.

See: pm/how-to/decisions.md
