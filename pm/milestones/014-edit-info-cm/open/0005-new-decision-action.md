# 0005 — Action "neue Decision-Datei"

## Goal
Aus dem UI laesst sich eine neue Decision anlegen: System ermittelt die
naechste globale Decision-Nummer, legt `pm/decisions/NNNN-<slug>.md` mit
Template an, oeffnet im Edit-Mode. Bestehende Decisions bleiben
read-only — Supersede laeuft ueber "neue Decision", nicht ueber Edit.

## Notes
- Decisions sind **append-only**. Diese Regel ist im Save-Endpoint
  (0001) schon serverseitig durchgesetzt; die Action hier ist die
  einzige legale Quelle fuer neue Decisions im UI.
- Wenn die neue Decision eine alte ersetzt, gehoert die Zeile
  `Supersedes: NNNN-<slug>` ins Template — bequemer Eingabe-Slot in
  der Form vorsehen.
- Nummerierung global, max+1 inklusive existierender Files.
- Template-Form steht in `pm/how-to/decisions.md`.
- Action gehoert in die Info-Sektion fuer Decisions; **nicht** als
  Edit-Button an alten Decisions.

## Done when
- Button "Neue Decision" sichtbar in der Decisions-Info-Sektion.
- Klick → Inline-Form mit Slug + (optional) "Supersedes: ..."-Feld.
- Submit → Datei `pm/decisions/NNNN-<slug>.md` existiert mit Template,
  korrekter naechster Nummer; UI springt in den Edit-Mode der neuen
  Decision.
- An bestehenden Decisions taucht weiterhin **kein** Edit-Button auf.
- Supersedes-Feld leer → keine Supersedes-Zeile in der Datei.
  Supersedes-Feld gefuellt → Zeile `Supersedes: <wert>` im Header.

## Out of scope
- Automatische Verlinkung der superseded Decision (kein Backref-
  Schreiben in die alte Datei).
- Hardcoded Liste alter Decisions.
- Validierung, dass die genannte Supersedes-Datei existiert.

See: pm/how-to/decisions.md
