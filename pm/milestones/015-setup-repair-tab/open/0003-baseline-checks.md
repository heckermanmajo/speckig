# 0003 — Baseline-Checks

## Goal
Der Setup-Tab zeigt einen sinnvollen Satz von Grund-Checks, die das
gesundheitlich Wichtige abdecken: PHP-Version, SPECKIG_ROOT, Repo-Pfad,
DB-Datei am kanonischen Ort, Vorhandensein/Aktualitaet der
`pm/how-to/`-Files.

## Notes
- "Auf dem aktuellen Stand" laesst sich nur gegen eine Referenz
  pruefen — die Referenz kommt aus 0005 (Baseline-Bundle). Bis das da
  ist, prueft der how-to-Check nur **Existenz**, nicht Inhalt. Ticket
  0005 zieht den Hash-Vergleich nach.
- PHP-Version min. 8.5 (siehe CLAUDE.md). Ergebnis bei 8.4: `fail` mit
  klarem Hint, kein `warn`.
- SPECKIG_ROOT-Check muss auch ohne gesetzte Env-Var sauber laufen
  (status `warn`/`fail` je nach Schwere).
- DB-Datei am kanonischen Ort: `app.sqlite` im Repo-Root (siehe
  Memory). Mehrere `app.sqlite*` ausserhalb sind ein `fail`.
- Vendor-Files (PHP unter `app/_share/vendor/`, JS unter
  `app/_share/js/` bzw. Vendor-Bereiche) muessen existieren — Check
  listet die erwarteten Pfade.

## Done when
- Setup-Tab zeigt mindestens diese Checks (Name + Status):
  PHP-Version, SPECKIG_ROOT, Repo-Pfad, DB-File-Lokation, how-to-Files
  vorhanden, Vendor-Files vorhanden.
- Auf einem gesunden Setup sind alle `ok`.
- Wenn man SPECKIG_ROOT testweise leert / `app.sqlite` versetzt,
  kippen die entsprechenden Checks auf `warn`/`fail` mit verstaendlichem
  Hint.
- Kein Check setzt automatisch `can_repair: true` — Reparatur-Wiring
  ist 0004.

## Out of scope
- Repair-Endpoints (0004).
- Hash-Vergleich mit Baseline (0005).
- Deployment-spezifische Checks (kommt in M016/0004).

See: pm/how-to/code_style.md
