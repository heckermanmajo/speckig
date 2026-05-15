<?php

declare(strict_types=1);

// @spec
// Setup-Checks-Runner (M015/0002).
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
// Check-Methoden als `[name, callable]`-Paare. Neue Checks in 0003+
// werden hier eingehaengt; an `run()` selbst muss niemand mehr
// schrauben.
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
        ["php_version",  "PHP-Version >= 8.5", [self::class, "check_php_version"]],
        ["speckig_root", "SPECKIG_ROOT gesetzt", [self::class, "check_speckig_root"]],
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
    // Beispiel-Check fuer den Skeleton-Lauf (M015/0002). Echte
    // Baseline-Checks kommen in 0003.
    //
    // Erwartung: `PHP_VERSION_ID >= 80500` (PHP 8.5+, siehe CLAUDE.md).
    // Trifft das zu, status=ok. Sonst status=fail mit der aktuellen
    // Version im Hint. Repair gibt es hier nicht — PHP-Upgrade ist
    // out-of-scope fuer den Repair-Endpoint.
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
            "hint"          => "Erwartet: PHP 8.5+. Gefunden: PHP " . PHP_VERSION,
            "can_repair"    => false,
            "repair_action" => "",
        ];
    }

    // @spec
    // check_speckig_root(): array
    //
    // Beispiel-Check fuer den Skeleton-Lauf (M015/0002). Echte
    // Baseline-Checks kommen in 0003.
    //
    // Erwartung: `getenv("SPECKIG_ROOT")` liefert einen non-empty String.
    // Trifft das zu, status=ok. Sonst status=warn — die Setup-View soll
    // bewusst auch ohne SPECKIG_ROOT etwas Sinnvolles zeigen koennen
    // (siehe 0001-Spec). Repair gibt es hier nicht; setzen muss der User
    // selbst (Shell-Profile, siehe M016).
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
