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

## Plan
- **Neue Datei**: `app/_share/setup_checks.php` (Namespace
  `_share`, statisches Funktions-Buendel `setup_checks` — lowercase
  laut Decision 0003).
- **API**:
  - `setup_checks::run(): array` — laeuft alle registrierten Checks
    durch und liefert eine Liste normalisierter Result-Arrays.
  - Result-Schema: `[
      "name"           => string,
      "status"         => "ok" | "warn" | "fail",
      "hint"           => string,
      "can_repair"     => bool,
      "repair_action"  => string | "",
    ]`.
  - Register-Mechanismus: einfache Konstante `setup_checks::CHECKS`
    mit Liste von Callable-Identifiern (`[self::class, "check_foo"]`)
    bzw. Inline-Liste in `run()`.
- **Exception-Isolation**: jeder Check-Aufruf in try/catch; bei
  Exception → Result `{status: "fail", hint: $e->getMessage(),
  can_repair: false, repair_action: ""}` mit `name` aus dem Check-Slug.
  `app::error_log()` mit Stacktrace.
- **Zwei Beispiel-Checks** (echte Logik kommt in 0003):
  - `check_php_version()`: `PHP_VERSION_ID >= 80500` → ok, sonst fail.
  - `check_speckig_root()`: `getenv("SPECKIG_ROOT")` non-empty → ok,
    sonst warn.
- **Rendering** in `app/setup.php`:
  - `$results = setup_checks::run();` direkt nach Header.
  - Foreach in einer `<table class="setup-checks">` mit Spalten
    Status / Name / Hint / Action.
  - Status als CSS-Klasse `.status-ok / .status-warn / .status-fail`
    fuer Faerbung (Styles in `app.css` ergaenzen).
- **Spec-Blocks**: Result-Schema in `setup_checks.php` dokumentieren,
  Rendering-Vertrag in `setup.php`.
- **Files touched**: `app/_share/setup_checks.php` (neu),
  `app/setup.php` (Rendering), `app/_share/css/app.css` (3 neue
  Klassen).

## Verifikation
- `php -l app/_share/setup_checks.php app/setup.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- `curl -s http://127.0.0.1:8086/setup.php | grep -c "setup-checks"` → 1.
- Browser auf setup.php: Tabelle zeigt mindestens 2 Zeilen (PHP-Version,
  SPECKIG_ROOT) mit Status-Indikatoren.
- Testcheck fuer Exception-Isolation: temporaer einen Check anlegen,
  der `throw new RuntimeException("boom")` macht; Setup-Seite zeigt
  ihn als `fail` mit `hint: boom`, andere Checks laufen weiter.
  Cleanup: Test-Check entfernen.
- `git status` clean.

## Out of scope (Plan)
- Vollstaendige Check-Liste (0003).
- Repair-Buttons (0004).
- AJAX-Re-Run.
