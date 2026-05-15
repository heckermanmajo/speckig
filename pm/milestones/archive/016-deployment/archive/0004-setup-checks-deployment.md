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

## Plan
- **Drei neue Checks in `app/_share/setup_checks.php`** (haengt am
  Runner aus M015/0002, nicht daneben):
  - `check_speckig_shell_function()`:
    - Sucht in `~/.bashrc` UND `~/.zshrc` nach dem Marker
      `# >>> speckig`. `getenv("HOME")` als Basis.
    - Gefunden → `ok`.
    - Nicht gefunden → `warn` mit Hint "Snippet via
      scripts/install.sh einrichten".
    - Keine `can_repair: true` (Server soll keine Shell-Configs
      schreiben).
  - `check_repo_path()`:
    - Erwartet `$HOME/Desktop/speckig` als Repo-Root.
    - Tatsaechlicher Repo-Root via `realpath(__DIR__ . "/../..")`.
    - Match → `ok`.
    - Mismatch → `warn`, hint nennt beide Pfade. Kein `fail` —
      Userin darf woanders liegen.
  - `check_port_8083()`:
    - `$socket = @fsockopen("127.0.0.1", 8083, $errno, $errstr,
      0.5);`
    - Verbindung steht → Port belegt → `warn` mit Hint
      "Port 8083 ist belegt — laeuft schon ein Speckig?".
    - Verbindung schlaegt fehl → `ok` "Port 8083 frei".
    - Kein `fail` (Belegung kann legitim sein).
- **Optionale Repair-Hilfe**: in dieser Iteration **keine**
  Repair-Buttons fuer Deployment-Checks. Wenn der User eine
  Bequemlichkeitsfunktion will, kann ein spaeteres Ticket einen
  Button "Installer-Kommando in Zwischenablage" via JS bauen — das
  ist heute YAGNI.
- **Spec-Bloecke** an jedem neuen Check.
- **Files touched**: `app/_share/setup_checks.php` (drei neue
  Check-Methoden + Registrierung).

## Verifikation
- `php -l app/_share/setup_checks.php` clean.
- Server `php -S 127.0.0.1:8086 -t app` run_in_background.
- Browser auf setup.php: die drei neuen Checks sichtbar.
- Shell-Function-Check: aktuell auf deiner Dev-Maschine wahrscheinlich
  `warn` (Snippet noch nicht installiert). Test mit kuenstlich
  praepariertem `HOME`:
  - `HOME=/tmp/fakehome` setzen (php-Server muss mit dem env neu
    starten), `echo "# >>> speckig" > /tmp/fakehome/.bashrc`; Check
    sollte dann `ok` zeigen.
- Repo-Path-Check: Repo liegt aktuell unter
  `/home/mo/Schreibtisch/speckig` (nicht `~/Desktop/speckig`) →
  `warn` zu erwarten.
- Port-8083-Check: ohne laufenden Server → `ok`. Mit `nc -l 8083` in
  einem zweiten Terminal → `warn`. (Achtung: User-Server-Memory —
  aktuell laeuft 8083 wahrscheinlich nicht, also `ok`. Bei laufendem
  User-Server `warn`.)
- `git status` clean.

## Out of scope (Plan)
- Repair-Aktionen fuer Deployment.
- Multi-Instanz-Detection.
- Telemetry / Phone-home.

## Done
- Drei neue Checks in `app/_share/setup_checks.php` plus Registrierung
  in `CHECKS` — Reihenfolge hinter den M015-Baseline-Checks:
  - `check_speckig_shell_function()`: Marker `# >>> speckig` in
    `$HOME/.bashrc` und `$HOME/.zshrc` via `str_contains()`. Existenz
    der Configs ist optional (eine reicht). `ok` falls Marker in einer
    Datei, sonst `warn` mit Hint auf `scripts/install.sh`. Wenn `HOME`
    leer ist → `warn` "nicht pruefbar". Kein `can_repair`.
  - `check_repo_path_default()`: vergleicht `realpath(__DIR__/../..)`
    gegen `$HOME/Desktop/speckig`. Realpath-Normalisierung auch fuer
    den Erwartungs-Pfad, damit Symlinks nicht falsch warnen; fallback
    auf literal compare wenn der Default nicht existiert. `warn`, kein
    `fail` — Konvention, nicht Zwang. Kein Repair.
  - `check_port_8083()`: `@fsockopen("127.0.0.1", 8083, ..., 0.5)`;
    Connection → `warn` "laeuft schon ein Speckig?", refused → `ok`.
    Socket wird `@fclose()`d falls offen. Kein Repair.
- Spec-Bloecke (Vertrag, Status-Mapping, "kein Repair"-Begruendung) an
  jedem neuen Check.
- Kein neuer Repair-Endpoint und kein neuer Eintrag in `REPAIR_IDS` —
  alle drei Checks sind explizit `can_repair:false`.

Files touched:
- `app/_share/setup_checks.php` (3 neue Check-Methoden, 3 neue
  Registrierungen).
- `pm/milestones/016-deployment/milestone.md` (Haekchen 0004 +
  `Status: planned` → `Status: done`).
- Ticket-Move `open/0004-setup-checks-deployment.md` →
  `archive/0004-setup-checks-deployment.md`.
- Milestone-Move `pm/milestones/016-deployment` →
  `pm/milestones/archive/016-deployment` (schliesst M016 ab).

Verifikation (Plan-konform):
- `php -l app/_share/setup_checks.php` → "No syntax errors detected".
- Server `SPECKIG_ROOT="$(pwd)" php -S 127.0.0.1:8086 -t app` (NIE
  8083), Smoketest gegen `/setup.php`:
  - `grep -oE 'status-(ok|warn|fail)' | sort | uniq -c` → `7 ok`,
    `2 warn`, 0 fail. Total 9 Checks (6 Baseline + 3 neu), Sanity-
    Zaehler `<tr>` → 10 (inkl. Header).
  - Shell-Function-Check: `warn` (Marker fehlt in `~/.bashrc`, kein
    `~/.zshrc` auf dem Rechner) — wie erwartet.
  - Repo-Path-Default: `warn` (Repo liegt unter
    `/home/mo/Schreibtisch/speckig`, nicht `~/Desktop/speckig`).
  - Port 8083: `ok` (User-Server lief zur Test-Zeit nicht).
- Streu-File-Check: `find … -name "app.sqlite*"` zeigt nur die
  kanonische `/home/mo/Schreibtisch/speckig/app.sqlite`.
- 8086-Server am Ende via `TaskStop`. 8083 nicht angefasst.
- `git status` clean nach Close-Commit.

Plan-Abweichungen:
- Slug fuer Default-Pfad-Check ist `repo_path_default` (Ticket-Plan
  hatte `check_repo_path` als Methoden-Namen vorgeschlagen, das wuerde
  mit dem bestehenden Baseline-Check aus M015/0003 kollidieren — der
  Subagent-Prompt hat das schon angefragt und gibt freie Hand).
- `check_speckig_shell_function` matcht den Marker als Substring
  (`str_contains`), nicht als Zeilenanfang. Damit deckt der Check auch
  den realen Snippet-Marker `# >>> speckig (managed by scripts/install.sh)`
  aus M016/0002 ab — `^# >>> speckig$` waere zu strikt.
