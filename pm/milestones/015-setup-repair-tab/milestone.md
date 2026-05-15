# 015 — Setup / Repair-Tab im Header

Goal: Vierter Tab im Header (nach Files, Plan, …) namens
`Setup / Repair`. Zeigt eine Selbst-Check-Seite: liegt das Repo am
erwarteten Ort? Sind alle `pm/how-to/`-Files vorhanden? Sind sie auf
dem aktuellen Stand (Vergleich mit einer "expected"-Liste)? Ist
SPECKIG_ROOT gesetzt? Laeuft die noetige PHP-Version (8.5+, siehe
CLAUDE.md)? Sind die Vendor-JS-/PHP-Files an Ort und Stelle? Plus pro
Befund ein optionaler `Repair`-Button (z.B. fehlende how-to-Datei aus
Template anlegen).

Status: planned

## Tickets
- [x] archive/0001-setup-tab-route.md — vierten Header-Tab in
      `app/_share/html/header.php` ergaenzen; neue Route `app/setup.php`;
      Header-Active-View `setup` unterstuetzen.
- [x] archive/0002-checks-runner.md — `_share\setup_checks::run()` mit
      einer Liste von Checks; jeder Check liefert `{name, status, hint,
      can_repair, repair_action}`. Status: ok / warn / fail.
- [ ] open/0003-baseline-checks.md — initiale Checkliste: PHP-Version,
      SPECKIG_ROOT, Repo-Pfad, Datenbank-File (canonical path,
      siehe Memory), how-to-Files vorhanden + Hashes.
- [ ] open/0004-repair-actions.md — POST-Endpoint `setup.php?action=
      repair&id=...`, fuehrt benannte Reparatur aus (z.B.
      `restore_how_to:bugs.md` legt fehlende how-to-Datei aus
      Template-Bundle an).
- [ ] open/0005-howto-baseline-bundle.md — How-to-Baseline als
      versioniertes Asset (z.B. `app/_share/setup/howto-baseline/`),
      damit Repair einen Referenz-Stand hat. Inkl. Decision, wie der
      Stand fortgeschrieben wird.

## Out of scope
- Automatische Repairs ohne Klick — alles ist user-getriggert.
- Self-Update / git pull aus dem UI.
- Diff-Anzeige zwischen gefundener und Baseline-Datei (kann ein
  spaeteres Ticket werden).
- Telemetry / Phone-home.

See: pm/ideas/milestone und ticket view.md
