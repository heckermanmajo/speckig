<?php

declare(strict_types=1);

// @spec
// Setup-Checks-Runner (M015/0002, Baseline-Liste in 0003).
// Zentrale Stelle, die eine Liste von Selbst-Checks ausfuehrt und pro
// Check ein **normalisiertes** Result-Array zurueckliefert. Die
// Setup-View (`app/setup.php`) rendert die Liste als Tabelle.
//
// Vertrag pro Result:
//   [
//     "name"          => string,   // Lesbarer Check-Name fuer das UI.
//     "status"        => "ok" | "warn" | "fail",
//     "hint"          => string,   // Frei-Text: Erklaerung / Messwert.
//     "can_repair"    => bool,     // true nur, wenn repair_action gesetzt ist.
//     "repair_action" => string,   // Identifier fuer Repair-Endpoint (0004),
//                                  //   leer wenn nichts zu reparieren ist.
//   ]
//
// Exception-Isolation: jeder Check laeuft in einem eigenen try/catch.
// Wirft ein Check, kippt das nicht den Lauf — der Check landet als
// `status: "fail"` mit `hint: <message>` im Ergebnis, der naechste Check
// laeuft trotzdem. `app::error_log()` haelt den Trace fest.
//
// Register-Mechanismus: Konstante `setup_checks::CHECKS` listet alle
// Check-Methoden als `[slug, name, callable]`-Tripel. Neue Checks werden
// hier eingehaengt; an `run()` selbst muss niemand mehr schrauben.
//
// Baseline-Checks (M015/0003): PHP-Version, SPECKIG_ROOT, Repo-Pfad,
// DB-Datei, how-to-Files, Vendor-Files. Alle Repair-Wiring ist
// out-of-scope hier — `can_repair: false` bei allen. Wiring kommt in
// 0004, der Hash-Vergleich gegen die Baseline in 0005.
// @end-spec

namespace _share;

// @spec
// Statisches Funktions-Buendel — keine Instanz, kein Zustand.
// Lowercase-Klassenname nach Decision 0003 (statische Funktions-Buendel
// wie `app`, `db`, `document` sind lowercase).
// @end-spec
class setup_checks
{

    // @spec
    // Registrierte Checks: Liste von `[slug, name, callable]`-Tripeln.
    // - `slug` ist der maschinenlesbare Identifier (snake_case, stabil
    //   ueber Renames hinweg). Wird in `run()` als Fallback-Name benutzt,
    //   wenn ein Check vor dem Liefern eines eigenen `name` wirft.
    // - `name` ist der UI-Name; darf umbenannt werden ohne IDs zu brechen.
    // - `callable` ist eine statische Methode auf dieser Klasse.
    //
    // Reihenfolge in dieser Liste = Reihenfolge in der UI-Tabelle.
    // @end-spec
    const CHECKS = [
        ["php_version",  "PHP-Version >= 8.5",   [self::class, "check_php_version"]],
        ["speckig_root", "SPECKIG_ROOT gesetzt", [self::class, "check_speckig_root"]],
        ["repo_path",    "Repo-Pfad erreichbar", [self::class, "check_repo_path"]],
        ["db_file",      "DB-Datei am kanonischen Ort", [self::class, "check_db_file"]],
        ["howto_files",  "pm/how-to-Files vorhanden",   [self::class, "check_howto_files"]],
        ["vendor_files", "Vendor-Files vorhanden",      [self::class, "check_vendor_files"]],
    ];

    // @spec
    // Erwartete pm/how-to/*.md-Files (M015/0003).
    //
    // Hart kodiert nach `ls pm/how-to/` zum Implementierungszeitpunkt.
    // Hash-Vergleich gegen eine Referenz-Baseline kommt in 0005 — hier
    // wird **nur Existenz** geprueft. Aenderungen an der Liste (neue
    // how-to-Files, Renames) gehen hier rein; ein fehlender oder neuer
    // Eintrag schlaegt sich direkt im Check-Status nieder.
    // @end-spec
    const HOWTO_FILES = [
        "at-bot.md",
        "audits.md",
        "brainstorm.md",
        "bugs.md",
        "code_style.md",
        "commit.md",
        "decision-tasks.md",
        "decisions.md",
        "ideas.md",
        "milestones.md",
        "process.md",
        "README.md",
        "reports.md",
        "spec.md",
        "terms.md",
        "testing.md",
    ];

    // @spec
    // Erwartete Vendor-Files (M015/0003).
    //
    // Pfade relativ zum Repo-Root. Stand zum Implementierungszeitpunkt:
    // - `app/_share/vendor/Parsedown.php` (PHP-Markdown, vendored vor M013).
    // - `app/_share/vendor/js/codemirror.min.js` (CM 5.65.21, M013/0001).
    // - `app/_share/vendor/css/codemirror.css` (CM-Default-CSS, M013/0001).
    // - Acht Mode-Files unter `app/_share/vendor/js/codemirror-modes/`
    //   (M013/0002): markdown, php, javascript, clike, shell, css, xml,
    //   yaml, htmlmixed. `htmlmixed` ist Dependency von `php`.
    //
    // Pfad-Layout weicht bewusst vom Ticket-Plan-Wortlaut ab — der Plan
    // sprach von `mode/<name>/<name>.js` und `codemirror.min.css`. Die
    // tatsaechlichen Pfade (siehe M013/0001 + 0002) sind hier
    // verbindlich; der Check muss das Reale pruefen, nicht eine
    // Wunschform.
    // @end-spec
    const VENDOR_FILES = [
        "app/_share/vendor/Parsedown.php",
        "app/_share/vendor/js/codemirror.min.js",
        "app/_share/vendor/css/codemirror.css",
        "app/_share/vendor/js/codemirror-modes/markdown.js",
        "app/_share/vendor/js/codemirror-modes/php.js",
        "app/_share/vendor/js/codemirror-modes/javascript.js",
        "app/_share/vendor/js/codemirror-modes/clike.js",
        "app/_share/vendor/js/codemirror-modes/shell.js",
        "app/_share/vendor/js/codemirror-modes/css.js",
        "app/_share/vendor/js/codemirror-modes/xml.js",
        "app/_share/vendor/js/codemirror-modes/yaml.js",
        "app/_share/vendor/js/codemirror-modes/htmlmixed.js",
    ];

    // @spec
    // run(): array
    //
    // Fuehrt alle in `CHECKS` registrierten Checks aus und liefert eine
    // Liste normalisierter Result-Arrays (siehe File-Header-Spec).
    //
    // Garantien:
    //   - Reihenfolge der Results == Reihenfolge der `CHECKS`-Konstante.
    //   - Wirft ein Check eine Throwable, wird sie hier gefangen; der
    //     Check landet als `status: "fail"` mit dem Throwable-Message als
    //     `hint`. Der Lauf bricht nicht ab.
    //   - Result-Schema wird hier nicht validiert; die Check-Methoden
    //     muessen es selbst liefern. Bei Exception baut `run()` ein
    //     vollstaendiges Result aus dem Slug.
    // @end-spec
    static function run(): array
    {
        $results = [];

        foreach (setup_checks::CHECKS as $registration)
        {
            $slug     = $registration[0];
            $name     = $registration[1];
            $callable = $registration[2];

            try
            {
                $result = call_user_func($callable);

                # Defensive: callable could in theory return a malformed
                # shape. Fill missing keys with safe defaults so the
                # renderer never crashes on a `null`.
                $results[] = setup_checks::normalize_result($result, $name);
            }
            catch (\Throwable $check_error)
            {
                app::error_log(
                    "setup_checks::run check '{$slug}' threw: "
                    . $check_error->getMessage()
                    . "\n"
                    . $check_error->getTraceAsString()
                );

                $results[] = [
                    "name"          => $name,
                    "status"        => "fail",
                    "hint"          => $check_error->getMessage(),
                    "can_repair"    => false,
                    "repair_action" => "",
                ];
            }
        }

        return $results;
    }

    // @spec
    // check_php_version(): array
    //
    // Vertrag: `PHP_VERSION_ID >= 80500` (PHP 8.5+, siehe CLAUDE.md).
    //   - status=ok  → Version reicht, Hint nennt die gefundene Version.
    //   - status=fail → Version zu alt (z.B. 8.4.x). Kein `warn` — wir
    //     entwickeln gegen 8.5+, und 8.4 produziert Deprecations (siehe
    //     bug/0001). Repair gibt es hier nicht; PHP-Upgrade ist
    //     out-of-scope fuer den Repair-Endpoint (User-Action ueber das
    //     Paket-Management des OS).
    // @end-spec
    static function check_php_version(): array
    {
        $php_version_is_supported = PHP_VERSION_ID >= 80500;

        if ($php_version_is_supported)
        {
            return [
                "name"          => "PHP-Version >= 8.5",
                "status"        => "ok",
                "hint"          => "Gefunden: PHP " . PHP_VERSION,
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        return [
            "name"          => "PHP-Version >= 8.5",
            "status"        => "fail",
            "hint"          => "PHP 8.5+ erforderlich, gefunden: " . PHP_VERSION,
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // check_speckig_root(): array
    //
    // Vertrag: `getenv("SPECKIG_ROOT")` liefert einen non-empty String.
    //   - status=ok   → env-Variable gesetzt, Hint zeigt den Wert.
    //   - status=warn → leer oder ungesetzt. Bewusst `warn`, nicht
    //     `fail`: die Setup-View ist explizit so gebaut, dass sie auch
    //     ohne SPECKIG_ROOT lauffaehig ist (siehe 0001-Spec). Setzen
    //     muss der User selbst (Shell-Profile, kommt mit M016).
    // @end-spec
    static function check_speckig_root(): array
    {
        $raw_speckig_root = getenv("SPECKIG_ROOT");

        $speckig_root_is_set = is_string($raw_speckig_root) && $raw_speckig_root !== "";

        if ($speckig_root_is_set)
        {
            return [
                "name"          => "SPECKIG_ROOT gesetzt",
                "status"        => "ok",
                "hint"          => "Gefunden: " . $raw_speckig_root,
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        return [
            "name"          => "SPECKIG_ROOT gesetzt",
            "status"        => "warn",
            "hint"          => "SPECKIG_ROOT ist leer oder ungesetzt.",
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // check_repo_path(): array
    //
    // Vertrag: das per `realpath(__DIR__ . "/../..")` aufgeloeste
    // Verzeichnis ist der Repo-Root und enthaelt die kanonischen
    // Top-Level-Marker `app/`, `pm/` und `CLAUDE.md`.
    //   - status=ok   → Pfad existiert und alle drei Marker sind da.
    //     Hint nennt den absoluten Pfad.
    //   - status=fail → Pfad nicht aufloesbar oder ein Marker fehlt.
    //     Hint nennt den geprueften Pfad und ggf. das fehlende Marker-
    //     File. Repair gibt es hier nicht — der Subagent kann das
    //     Filesystem nicht reparieren, wenn das Repo selbst weg ist.
    // @end-spec
    static function check_repo_path(): array
    {
        $name = "Repo-Pfad erreichbar";

        $repo_root_abs = realpath(__DIR__ . "/../..");

        $repo_root_resolved = is_string($repo_root_abs) && is_dir($repo_root_abs);

        if (! $repo_root_resolved)
        {
            return [
                "name"          => $name,
                "status"        => "fail",
                "hint"          => "Repo-Root nicht aufloesbar via realpath(__DIR__/../..).",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        $expected_markers = ["app", "pm", "CLAUDE.md"];

        $missing_markers = [];

        foreach ($expected_markers as $marker)
        {
            $marker_path = $repo_root_abs . "/" . $marker;

            $marker_exists = file_exists($marker_path);

            if (! $marker_exists)
            {
                $missing_markers[] = $marker;
            }
        }

        $all_markers_present = count($missing_markers) === 0;

        if ($all_markers_present)
        {
            return [
                "name"          => $name,
                "status"        => "ok",
                "hint"          => "Repo-Root: " . $repo_root_abs,
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        return [
            "name"          => $name,
            "status"        => "fail",
            "hint"          => "Repo-Root: " . $repo_root_abs
                . " — fehlende Marker: " . implode(", ", $missing_markers),
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // check_db_file(): array
    //
    // Vertrag: genau eine SQLite-Datei `app.sqlite` direkt im Repo-Root
    // (kanonischer Pfad laut User-Memory). Streu-Kopien in Unterordnern
    // sind ein Stilbruch — finden wir welche, dann `warn`.
    //   - status=ok   → `<repo>/app.sqlite` existiert und keine
    //     `app.sqlite*`-Dateien in Unterordnern.
    //   - status=warn → kanonische Datei da, aber zusaetzliche
    //     `app.sqlite*` in Unterordnern (z.B. WAL/SHM an falscher
    //     Stelle, Streu-Kopien). Hint listet die Streu-Pfade.
    //   - status=fail → `<repo>/app.sqlite` fehlt. Hint nennt den
    //     erwarteten Pfad.
    // `.git/` wird beim Streu-Scan ausgeschlossen — dort liegen keine
    // SQLite-Files, und der Scan dort waere teuer.
    // @end-spec
    static function check_db_file(): array
    {
        $name = "DB-Datei am kanonischen Ort";

        $repo_root_abs = realpath(__DIR__ . "/../..");

        $repo_root_resolved = is_string($repo_root_abs) && is_dir($repo_root_abs);

        if (! $repo_root_resolved)
        {
            return [
                "name"          => $name,
                "status"        => "fail",
                "hint"          => "Repo-Root nicht aufloesbar — DB-Check uebersprungen.",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        $canonical_db_path = $repo_root_abs . "/app.sqlite";

        $canonical_db_exists = is_file($canonical_db_path);

        if (! $canonical_db_exists)
        {
            return [
                "name"          => $name,
                "status"        => "fail",
                "hint"          => "Erwartet: " . $canonical_db_path . " (fehlt).",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        # Rekursiver Streu-Scan: alle `app.sqlite*`-Files unterhalb von
        # Repo-Root finden, `.git/` ausschliessen, kanonisches File
        # selbst ignorieren.
        $stray_paths = setup_checks::find_stray_sqlite_files($repo_root_abs, $canonical_db_path);

        $no_stray_files = count($stray_paths) === 0;

        if ($no_stray_files)
        {
            return [
                "name"          => $name,
                "status"        => "ok",
                "hint"          => "Gefunden: " . $canonical_db_path,
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        return [
            "name"          => $name,
            "status"        => "warn",
            "hint"          => "Kanonisch ok, aber Streu-Files in Unterordnern: "
                . implode(", ", $stray_paths),
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // find_stray_sqlite_files($repo_root_abs, $canonical_db_path): array
    //
    // Helper fuer `check_db_file()`. Sucht rekursiv unterhalb von
    // `$repo_root_abs` nach Files mit Praefix `app.sqlite` (deckt
    // `app.sqlite`, `app.sqlite-wal`, `app.sqlite-shm`, `.bak`-Kopien),
    // schliesst `.git/` aus und liefert relative Pfade zum Repo-Root.
    // Das kanonische File (`<repo>/app.sqlite`) wird gefiltert.
    //
    // Privat: nur von `check_db_file()` aufgerufen. Aussenstehende
    // sollten den Stand der DB ueber den Check abfragen, nicht ueber
    // diesen Helper.
    // @end-spec
    private static function find_stray_sqlite_files(string $repo_root_abs, string $canonical_db_path): array
    {
        $stray_paths = [];

        $directory_iterator = new \RecursiveDirectoryIterator(
            $repo_root_abs,
            \FilesystemIterator::SKIP_DOTS
        );

        $filter = new \RecursiveCallbackFilterIterator(
            $directory_iterator,
            function (\SplFileInfo $info) use ($repo_root_abs): bool
            {
                $is_dot_git = $info->isDir()
                    && $info->getFilename() === ".git"
                    && dirname($info->getPathname()) === $repo_root_abs;

                # Skip .git tree entirely.
                if ($is_dot_git)
                {
                    return false;
                }

                return true;
            }
        );

        $recursive_iterator = new \RecursiveIteratorIterator(
            $filter,
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($recursive_iterator as $file_info)
        {
            $is_file = $file_info instanceof \SplFileInfo && $file_info->isFile();

            if (! $is_file)
            {
                continue;
            }

            $filename = $file_info->getFilename();

            $matches_sqlite_glob = str_starts_with($filename, "app.sqlite");

            if (! $matches_sqlite_glob)
            {
                continue;
            }

            $absolute_path = $file_info->getPathname();

            $is_canonical = $absolute_path === $canonical_db_path;

            if ($is_canonical)
            {
                continue;
            }

            # Relativ zum Repo-Root fuer lesbare Hints.
            $relative_path = substr($absolute_path, strlen($repo_root_abs) + 1);

            $stray_paths[] = $relative_path;
        }

        sort($stray_paths);

        return $stray_paths;
    }

    // @spec
    // check_howto_files(): array
    //
    // Vertrag: jede Datei in `setup_checks::HOWTO_FILES` existiert
    // unter `<repo>/pm/how-to/<name>`.
    //   - status=ok   → alle Files vorhanden.
    //   - status=fail → mindestens eines fehlt. Hint listet die
    //     fehlenden Namen (kein Pfad — der ist immer `pm/how-to/`).
    //     `fail`, nicht `warn`: how-to-Files sind verbindliche
    //     Konventions-Doku, ohne sie kippt der Process.
    // Hash-Vergleich gegen eine Referenz-Baseline kommt in 0005, hier
    // nur Existenz.
    // @end-spec
    static function check_howto_files(): array
    {
        $name = "pm/how-to-Files vorhanden";

        $repo_root_abs = realpath(__DIR__ . "/../..");

        $repo_root_resolved = is_string($repo_root_abs) && is_dir($repo_root_abs);

        if (! $repo_root_resolved)
        {
            return [
                "name"          => $name,
                "status"        => "fail",
                "hint"          => "Repo-Root nicht aufloesbar — how-to-Check uebersprungen.",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        $howto_dir = $repo_root_abs . "/pm/how-to";

        $missing_files = [];

        foreach (setup_checks::HOWTO_FILES as $howto_filename)
        {
            $howto_path = $howto_dir . "/" . $howto_filename;

            $howto_exists = file_exists($howto_path);

            if (! $howto_exists)
            {
                $missing_files[] = $howto_filename;
            }
        }

        $all_present = count($missing_files) === 0;

        if ($all_present)
        {
            return [
                "name"          => $name,
                "status"        => "ok",
                "hint"          => count(setup_checks::HOWTO_FILES) . " Files unter pm/how-to/ vorhanden.",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        return [
            "name"          => $name,
            "status"        => "fail",
            "hint"          => "Fehlende how-to-Files: " . implode(", ", $missing_files),
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // check_vendor_files(): array
    //
    // Vertrag: jede Datei in `setup_checks::VENDOR_FILES` existiert
    // (Pfade relativ zum Repo-Root).
    //   - status=ok   → alle Files vorhanden.
    //   - status=fail → mindestens eines fehlt. Hint listet die
    //     fehlenden Pfade. `fail`, nicht `warn`: Vendor-Files sind
    //     Laufzeit-Abhaengigkeiten (Parsedown fuer Markdown-Render,
    //     CodeMirror fuer den Editor); ohne sie kippt die UI.
    // @end-spec
    static function check_vendor_files(): array
    {
        $name = "Vendor-Files vorhanden";

        $repo_root_abs = realpath(__DIR__ . "/../..");

        $repo_root_resolved = is_string($repo_root_abs) && is_dir($repo_root_abs);

        if (! $repo_root_resolved)
        {
            return [
                "name"          => $name,
                "status"        => "fail",
                "hint"          => "Repo-Root nicht aufloesbar — Vendor-Check uebersprungen.",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        $missing_paths = [];

        foreach (setup_checks::VENDOR_FILES as $vendor_relative_path)
        {
            $vendor_abs_path = $repo_root_abs . "/" . $vendor_relative_path;

            $vendor_exists = file_exists($vendor_abs_path);

            if (! $vendor_exists)
            {
                $missing_paths[] = $vendor_relative_path;
            }
        }

        $all_present = count($missing_paths) === 0;

        if ($all_present)
        {
            return [
                "name"          => $name,
                "status"        => "ok",
                "hint"          => count(setup_checks::VENDOR_FILES) . " Vendor-Files vorhanden.",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        return [
            "name"          => $name,
            "status"        => "fail",
            "hint"          => "Fehlende Vendor-Files: " . implode(", ", $missing_paths),
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // normalize_result($raw, $fallback_name): array
    //
    // Fuellt fehlende Schluessel mit sicheren Defaults und coerced Typen,
    // damit der Renderer niemals auf `null` zugreift. Behebt **keine**
    // semantischen Fehler; falsche Werte bleiben falsch sichtbar.
    //
    // Defaults:
    //   - name          => $fallback_name (Name aus der CHECKS-Registry)
    //   - status        => "fail" (wenn ungueltig oder fehlt)
    //   - hint          => ""
    //   - can_repair    => false
    //   - repair_action => ""
    //
    // Privat: Aufrufer muessen Results niemals selbst normalisieren —
    // sie kommen entweder aus den Check-Methoden oder aus dem
    // Exception-Handler.
    // @end-spec
    private static function normalize_result(mixed $raw, string $fallback_name): array
    {
        $allowed_statuses = ["ok", "warn", "fail"];

        $raw_is_array = is_array($raw);

        if (! $raw_is_array)
        {
            return [
                "name"          => $fallback_name,
                "status"        => "fail",
                "hint"          => "Check lieferte kein Array.",
                "can_repair"    => false,
                "repair_action" => "",
            ];
        }

        $name = isset($raw["name"]) && is_string($raw["name"]) && $raw["name"] !== ""
            ? $raw["name"]
            : $fallback_name;

        $raw_status        = $raw["status"] ?? "";
        $raw_status_is_ok  = is_string($raw_status) && in_array($raw_status, $allowed_statuses, true);
        $status            = $raw_status_is_ok ? $raw_status : "fail";

        $hint = isset($raw["hint"]) && is_string($raw["hint"]) ? $raw["hint"] : "";

        $can_repair = isset($raw["can_repair"]) ? (bool) $raw["can_repair"] : false;

        $repair_action = isset($raw["repair_action"]) && is_string($raw["repair_action"])
            ? $raw["repair_action"]
            : "";

        # can_repair only "true" if there's an action to dispatch.
        $repair_is_dispatchable = $can_repair && $repair_action !== "";

        return [
            "name"          => $name,
            "status"        => $status,
            "hint"          => $hint,
            "can_repair"    => $repair_is_dispatchable,
            "repair_action" => $repair_action,
        ];
    }

}
