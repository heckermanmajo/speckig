# 0004 — Setup/Repair-Checks fuer Deployment

## Goal
Der Setup/Repair-Tab (M015) zeigt deployment-spezifische Checks: ist die
`speckig`-Shellfunktion eingerichtet, liegt das Repo am erwarteten
Pfad, ist Port 8083 belegt.

## Notes
- "Shellfunktion eingerichtet" kann der Server nur indirekt pruefen —
  z.B. Marker-Kommentar in `~/.bashrc`/`~/.zshrc` suchen. Das ist
  eine Heuristik, nicht beweisbar; Status `warn` bei nicht gefunden,
  nicht `fail`.
- Port-8083-Check: einfach `fsockopen` o.ae. — wenn belegt durch eine
  fremde App, das ist eher `warn` (Setup-Tab kann es nicht beheben)
  als `fail`.
- Repo-Pfad-Check: erwarteter Default `~/Desktop/speckig`; abweichender
  Pfad ist `warn`, weil der User auch absichtlich woanders liegen kann.
- Repair-Aktionen sparsam — eigentliche Reparatur ist
  "Installer-Script laufen lassen", was der Server nicht selbst tun
  sollte. Eher Repair → Anleitung kopieren.
- Diese Checks haengen am Runner aus M015/0002 — bauen, nicht
  daneben.

## Done when
- Auf einem Rechner ohne `speckig`-Funktion in der Shell zeigt der
  Tab den entsprechenden `warn`-Check mit Anleitung.
- Auf einem Rechner, wo Repo nicht unter `~/Desktop/speckig` liegt,
  zeigt der Tab `warn` + tatsaechlichen Pfad.
- Port-Check zeigt klar: 8083 frei vs. belegt.
- Falls Repair-Buttons hinzukommen, sind sie nur informativ (z.B.
  "kopiere Installer-Kommando in die Zwischenablage") — keine
  automatische Shell-Manipulation.

## Out of scope
- Tatsaechliche Shell-Manipulation aus dem UI.
- Multi-Instanz-Checks (zwei Speckig parallel).
- Telemetry.

See: pm/milestones/015-setup-repair-tab/milestone.md
