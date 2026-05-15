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

## Done
- `app/_share/setup_checks.php` neu angelegt: Namespace `_share`,
  statisches Funktions-Buendel `setup_checks` (lowercase nach Decision
  0003), Konstante `CHECKS` als Liste von `[slug, name, callable]`-
  Tripeln (Slug fuer Logging, Name fuers UI, Callable als statische
  Methode). `setup_checks::run()` iteriert die Konstante in fixer
  Reihenfolge, ruft jeden Check via `call_user_func()` auf, normalisiert
  das Result (siehe unten) und faengt jede `\Throwable` einzeln ab — bei
  Crash wird ein vollstaendiges Result mit `status:"fail"`,
  `hint: $e->getMessage()` und Trace via `app::error_log()` ans Ergebnis-
  Array gehaengt, der naechste Check laeuft weiter.
  - Result-Schema (im File-Spec-Block dokumentiert):
    `{name:string, status:"ok"|"warn"|"fail", hint:string,
    can_repair:bool, repair_action:string}`.
  - Private `normalize_result()`: fuellt fehlende Schluessel mit
    sicheren Defaults, coerced ungueltige Statuswerte auf `"fail"`,
    erzwingt `can_repair === true` nur, wenn auch `repair_action`
    non-empty ist (Vertragsregel aus dem Ticket).
- Zwei Beispiel-Checks (echte Logik kommt in 0003):
  - `check_php_version()`: `PHP_VERSION_ID >= 80500` -> ok, sonst fail.
    Hint nennt die gefundene Version.
  - `check_speckig_root()`: `getenv("SPECKIG_ROOT")` non-empty -> ok,
    sonst warn (warn, nicht fail — Setup-View soll bewusst auch ohne
    SPECKIG_ROOT lauffaehig sein, siehe 0001-Spec).
- `app/setup.php`: Spec-Block um Render-Vertrag erweitert (Status als
  CSS-Klasse `.status-<status>` auf der Status-Spalte, alle Text-Felder
  durch `app::escape()`, Action-Spalte erstmal leer mit Kommentar-
  Hinweis auf 0004). Direkt nach `init.php`-Include laeuft
  `$check_results = setup_checks::run();`, gerendert als
  `<table class="setup-checks">` mit Thead (Status/Check/Hint/Action) und
  einem `<tr>` pro Result.
- `app/_share/css/app.css`: neuer Block "Setup-View (M015/0002)" am
  Ende. `.setup-checks` mit `border-collapse`, `<th>`-Styling in
  Uppercase-Mini-Caps analog `plan-section-heading`. Drei
  Status-Klassen: `.status-ok #2a8d2a`, `.status-warn #c97a00`,
  `.status-fail #b00` — `.status-fail` deckt sich farblich mit dem
  `.btn-delete` aus M013/0005, damit "rot = kaputt" konsistent bleibt.

Files touched:
- `app/_share/setup_checks.php` (neu).
- `app/setup.php` (Render).
- `app/_share/css/app.css` (3 Status-Klassen + Tabellen-Layout).
- `pm/milestones/015-setup-repair-tab/milestone.md` (Haekchen +
  archive/-Pfad).
- ticket selbst nach `archive/`.

Smoketest-Belege (Server `SPECKIG_ROOT=... php -S 127.0.0.1:8086 -t app`):
- `php -l app/_share/setup_checks.php app/setup.php` -> clean.
- `curl /setup.php | grep -c "setup-checks"` -> 1.
- `curl /setup.php | grep -c "status-ok"` -> 2 (beide Beispiel-Checks
  gruen auf der Dev-Maschine, weil PHP 8.5.6 und SPECKIG_ROOT gesetzt).
- `curl /setup.php | grep -c 'aria-current="page"'` -> 1 (Setup-Tab
  aktiv).
- `curl /index.php`, `/plan.php`, `/info.php | grep -c 'href="/setup.php"'`
  -> 1 (Header zeigt setup-Link auf allen Tabs).
- Exception-Isolation manuell verifiziert: temporaer einen
  `check_boom()` ergaenzt, der `RuntimeException("boom")` wirft, dritter
  Eintrag in CHECKS davor. Setup-Seite rendert 3 Zeilen, mittlere mit
  `status-fail` + Hint `boom`, php_version und speckig_root weiterhin
  `ok`. Test-Check und sein CHECKS-Eintrag vor dem Close-Commit
  entfernt.
- Schema-Probe via `php -r` (manuelle Requires von app.php +
  setup_checks.php, kein init.php-Roundtrip): `var_dump(setup_checks::
  run())` zeigt Array mit 2 Elementen, je 5 Schluessel (name, status,
  hint, can_repair, repair_action) in genau dieser Reihenfolge.
- Streu-File-Check: keine `*.tmp.*`-Dateien, `app.sqlite` nur kanonisch
  unter `./app.sqlite`.
