<?php

declare(strict_types=1);

// @spec
// Setup/Repair-View (M015/0001, ausgebaut in 0002, Baseline-Checks in
// 0003, Repair-Endpoint in 0004, How-to-Baseline-Bundle + echter
// restore_how_to-Handler in 0005).
//
// Vierte Hauptansicht des Speckig-UI. Bewusst so robust gebaut, dass sie
// auch dann etwas Sinnvolles anzeigen kann, wenn die Welt drumherum
// kaputt ist (kein SPECKIG_ROOT, fehlende how-to-Files, ...) — sie ist
// genau fuer diesen Fall da.
//
// Linker Bereich (`<nav>`): in 0001/0002 leer. Die Sidebar (Filter / Re-
// Run / o.ae.) kommt in spaeteren Tickets.
//
// Rechter Bereich (`<article id="content">`): seit 0002 wird hier eine
// Tabelle `<table class="setup-checks">` mit den Ergebnissen von
// `_share\setup_checks::run()` gerendert.
//
// Render-Vertrag fuer ein Check-Result (siehe `app/_share/setup_checks.php`
// fuer das Schema):
//   - `status` wird zusaetzlich zum Text-Label als CSS-Klasse
//     `.status-ok` / `.status-warn` / `.status-fail` auf das `<td>` mit
//     dem Status-Label gehaengt.
//   - `name`, `hint`, `repair_action` laufen alle durch `app::escape()`.
//   - Action-Spalte: bei `can_repair:true` wird ein
//     `<button data-repair-id="<repair_action>">Repair</button>` in
//     die Zelle gerendert. Der Click-Handler (siehe setup_loader.js)
//     POSTet an den unten beschriebenen Endpoint und reloaded die Seite.
//     Bei `can_repair:false` bleibt die Zelle leer.
//
// Repair-Endpoint (M015/0004):
//   POST ?action=repair&id=<repair-id>
//
//   - `id` MUSS in der Whitelist `REPAIR_IDS` stehen. Whitelist statt
//     freier Eingabe ist hart: jede neue Repair-Art bekommt einen
//     festen, hier eingetragenen Identifier. Unbekannte ID → HTTP 400 +
//     `{ok:false, status:"unknown_id"}` + `app::error_log()`.
//   - Dispatch laeuft ueber den Wert in der Whitelist (Handler-Name,
//     bisher nur `restore_how_to`). Die ID selbst hat das Format
//     `<handler>:<argument>`; das Argument geht in den Handler-Call.
//   - Seit M015/0005 fuehrt `restore_how_to_handler` die konkrete
//     Wiederherstellung aus dem Baseline-Bundle unter
//     `app/_share/setup/howto-baseline/` aus: Live-Datei fehlt →
//     atomar restored; Live existiert → `unchanged` (Drift-Schutz,
//     Decision 0008). I/O-Fehler liefern HTTP 200 + `{ok:false,
//     status:"io_error"}` — der HTTP-Status bleibt 200, damit der
//     JS-Loader einen einheitlichen Read-Json-Pfad hat.
//   - Antwort ist immer JSON, auch im Erfolgsfall.
//   - Jeder Repair-Aufruf wird via `app::error_log()` protokolliert
//     (ID + Resultat-Status). Logging laeuft BEVOR das Response-JSON
//     rausgeht, damit auch eine spaetere Exception im exit-Pfad nichts
//     verschluckt.
//   - Endpoint sitzt VOR dem GET-Rendering, analog zum Method-Dispatch
//     in `pm.php`. Bei POST-Repair wird `exit()` aufgerufen, das
//     HTML-Rendering kommt nicht zum Zug.
//
// Header: `_share\html\header::render("setup", ...)` — vierter Tab nach
// Files, Plan, Info. Stylesheet: `app/_share/css/app.css` (geteilt mit
// Tree-, Plan- und Info-View). JS: `app/_share/js/setup_loader.js`
// (M015/0004) bindet die Repair-Buttons.
// @end-spec

use _share\app;
use _share\html\header;
use _share\setup_checks;

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

# --- Repair-ID-Whitelist (M015/0004) ----------------------------------------

// @spec
// REPAIR_IDS: feste Whitelist der bekannten Repair-Aktionen.
//
// Key: vollqualifizierte Repair-ID, wie sie der Client schickt (URL-
// Parameter `id`). Wert: Handler-Name (snake_case), den der Dispatcher
// zur Auswahl der konkreten Repair-Routine benutzt. Die ID selbst hat
// das Format `<handler>:<argument>` — der Dispatcher trennt am Doppel-
// punkt und reicht das Argument an den Handler weiter.
//
// Aktueller Stand:
//   - `restore_how_to:<filename>` — legt eine fehlende
//     `pm/how-to/<filename>` aus dem Baseline-Bundle (M015/0005) wieder
//     an. Solange 0005 nicht da ist, antwortet der Handler mit
//     `not_implemented`. Filenames sind hart kodiert in genau dem
//     Stand, der auch in `setup_checks::HOWTO_FILES` steht — die beiden
//     Listen muessen synchron bleiben. Wenn eine neue `pm/how-to/*.md`
//     dazukommt, muss sie hier UND in HOWTO_FILES eingetragen werden.
//
// Whitelist statt freier Eingabe ist Pflicht (siehe Ticket-Notes): nur
// hier eingetragene IDs koennen ueberhaupt ausgefuehrt werden. Alles
// andere → 400.
// @end-spec
const REPAIR_IDS = [
    "restore_how_to:at-bot.md"         => "restore_how_to",
    "restore_how_to:audits.md"         => "restore_how_to",
    "restore_how_to:brainstorm.md"     => "restore_how_to",
    "restore_how_to:bugs.md"           => "restore_how_to",
    "restore_how_to:code_style.md"     => "restore_how_to",
    "restore_how_to:commit.md"         => "restore_how_to",
    "restore_how_to:decision-tasks.md" => "restore_how_to",
    "restore_how_to:decisions.md"      => "restore_how_to",
    "restore_how_to:ideas.md"          => "restore_how_to",
    "restore_how_to:milestones.md"     => "restore_how_to",
    "restore_how_to:process.md"        => "restore_how_to",
    "restore_how_to:README.md"         => "restore_how_to",
    "restore_how_to:reports.md"        => "restore_how_to",
    "restore_how_to:spec.md"           => "restore_how_to",
    "restore_how_to:terms.md"          => "restore_how_to",
    "restore_how_to:testing.md"        => "restore_how_to",
];

// @spec
// restore_how_to_handler($filename): array
//
// Vertrag (M015/0004 + 0005): legt eine fehlende
// `pm/how-to/<filename>` aus dem Baseline-Bundle wieder an. Idempotent
// — zweimaliger Aufruf darf nichts kaputt machen.
//
// Ablauf:
//   - Defensive Whitelist-Pruefung: `restore_how_to:<filename>` muss in
//     `REPAIR_IDS` stehen. Der aufrufende Dispatcher hat das zwar schon
//     geprueft, hier nochmal explizit, damit dieser Handler nicht
//     ueber direkten PHP-Aufruf umgangen werden kann.
//   - Quelle: `<repo>/app/_share/setup/howto-baseline/<filename>`.
//     Existiert die nicht, ist das Bundle kaputt → HTTP 200 +
//     `{ok:false, status:"baseline_missing"}`. Bewusst 200, nicht 500:
//     definiertes Resultat, kein Server-Error.
//   - Ziel: `<repo>/pm/how-to/<filename>`.
//   - Existiert das Ziel schon → `{ok:true, status:"unchanged"}` ohne
//     Schreibvorgang. Schuetzt vor versehentlichem Ueberschreiben einer
//     lokal abweichenden how-to-Datei (Drift-Fall, Decision 0008).
//   - Existiert das Ziel nicht → atomar schreiben via tmp+rename. Bei
//     Erfolg → `{ok:true, status:"restored", path:"pm/how-to/<name>"}`.
//   - I/O-Fehler werden geloggt und liefern HTTP 200 +
//     `{ok:false, status:"io_error"}`; HTTP-Status bleibt bei 200,
//     damit der JS-Loader einen einheitlichen JSON-Read-Pfad hat. Der
//     Endpoint-Dispatcher setzt den HTTP-Code, nicht dieser Handler.
//
// Pfad-Sicherheit: `$filename` kommt aus dem Argument-Teil einer
// REPAIR_ID, die Whitelist garantiert reine Basisnamen ohne Slashes
// oder `..`. Trotzdem nutzt der Handler ausschliesslich basename() vor
// dem Zusammenbau, damit der Schutz lokal sichtbar ist.
// @end-spec
function restore_how_to_handler(string $filename): array
{
    $repair_id = "restore_how_to:" . $filename;

    $repair_id_is_known = isset(REPAIR_IDS[$repair_id]);

    if (! $repair_id_is_known)
    {
        app::error_log("setup.php restore_how_to rejected unknown filename: " . $filename);
        return [
            "ok"      => false,
            "status"  => "unknown_id",
            "message" => "Unbekannter how-to-Filename.",
            "id"      => $repair_id,
        ];
    }

    # Defense in depth: nur den Basename verwenden, kein Pfad.
    $safe_filename = basename($filename);

    $repo_root_abs = realpath(__DIR__ . "/..");

    $repo_root_resolved = is_string($repo_root_abs) && is_dir($repo_root_abs);

    if (! $repo_root_resolved)
    {
        app::error_log("setup.php restore_how_to could not resolve repo root.");
        return [
            "ok"      => false,
            "status"  => "io_error",
            "message" => "Repo-Root nicht aufloesbar.",
            "id"      => $repair_id,
        ];
    }

    $baseline_path = $repo_root_abs . "/app/_share/setup/howto-baseline/" . $safe_filename;
    $live_path     = $repo_root_abs . "/pm/how-to/" . $safe_filename;
    $live_rel      = "pm/how-to/" . $safe_filename;

    $baseline_exists = is_file($baseline_path);

    if (! $baseline_exists)
    {
        app::error_log("setup.php restore_how_to baseline missing: " . $baseline_path);
        return [
            "ok"      => false,
            "status"  => "baseline_missing",
            "message" => "Baseline-Datei nicht gefunden.",
            "id"      => $repair_id,
        ];
    }

    $live_exists = file_exists($live_path);

    if ($live_exists)
    {
        # Idempotenz + Drift-Schutz: nicht ueberschreiben, wenn schon was
        # da ist (siehe Decision 0008).
        return [
            "ok"     => true,
            "status" => "unchanged",
            "id"     => $repair_id,
            "path"   => $live_rel,
        ];
    }

    $baseline_contents = file_get_contents($baseline_path);

    $baseline_read_ok = $baseline_contents !== false;

    if (! $baseline_read_ok)
    {
        app::error_log("setup.php restore_how_to could not read baseline: " . $baseline_path);
        return [
            "ok"      => false,
            "status"  => "io_error",
            "message" => "Baseline-Inhalt konnte nicht gelesen werden.",
            "id"      => $repair_id,
        ];
    }

    # Atomar via tmp+rename schreiben — vermeidet halbgeschriebene Files,
    # falls der Prozess waehrend write() abbricht.
    $tmp_path = $live_path . ".tmp." . bin2hex(random_bytes(4));

    $bytes_written = file_put_contents($tmp_path, $baseline_contents);

    $write_ok = $bytes_written !== false;

    if (! $write_ok)
    {
        app::error_log("setup.php restore_how_to could not write tmp: " . $tmp_path);
        return [
            "ok"      => false,
            "status"  => "io_error",
            "message" => "Schreiben der Tmp-Datei fehlgeschlagen.",
            "id"      => $repair_id,
        ];
    }

    $rename_ok = rename($tmp_path, $live_path);

    if (! $rename_ok)
    {
        # Tmp aufraeumen, damit kein Streu-File liegen bleibt.
        @unlink($tmp_path);
        app::error_log("setup.php restore_how_to rename failed: " . $tmp_path . " -> " . $live_path);
        return [
            "ok"      => false,
            "status"  => "io_error",
            "message" => "Rename der Tmp-Datei fehlgeschlagen.",
            "id"      => $repair_id,
        ];
    }

    return [
        "ok"     => true,
        "status" => "restored",
        "id"     => $repair_id,
        "path"   => $live_rel,
    ];
}

# --- Method-Dispatch: POST ?action=repair ----------------------------------
# Liegt VOR dem Header-Setup und Rendering, damit der Endpoint eigene
# Header (Content-Type: application/json) setzen kann und das HTML nicht
# anlaeuft. Analog zum Method-Dispatch in `pm.php`.

$method_is_post   = $_SERVER["REQUEST_METHOD"] === "POST";
$action           = isset($_GET["action"]) ? (string) $_GET["action"] : "";
$action_is_repair = $action === "repair";

if ($method_is_post && $action_is_repair)
{
    header("Content-Type: application/json");

    $raw_repair_id = isset($_GET["id"]) ? (string) $_GET["id"] : "";

    $repair_id_is_known = $raw_repair_id !== "" && isset(REPAIR_IDS[$raw_repair_id]);

    if (! $repair_id_is_known)
    {
        app::error_log(
            "setup.php repair rejected unknown id: "
            . ($raw_repair_id === "" ? "<empty>" : $raw_repair_id)
        );
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "status"  => "unknown_id",
            "message" => "Unbekannte Repair-ID.",
        ]));
    }

    $handler_name = REPAIR_IDS[$raw_repair_id];

    # ID-Format ist `<handler>:<argument>` — Argument hinter dem ersten
    # Doppelpunkt extrahieren. Whitelist garantiert, dass diese Form
    # eingehalten wird; wir splitten trotzdem defensiv mit Limit 2.
    $id_parts        = explode(":", $raw_repair_id, 2);
    $repair_argument = isset($id_parts[1]) ? $id_parts[1] : "";

    $handler_is_restore_how_to = $handler_name === "restore_how_to";

    if ($handler_is_restore_how_to)
    {
        $repair_result = restore_how_to_handler($repair_argument);

        $result_status = isset($repair_result["status"]) && is_string($repair_result["status"])
            ? $repair_result["status"]
            : "unknown";

        app::error_log(
            "setup.php repair id=" . $raw_repair_id
            . " handler=restore_how_to result=" . $result_status
        );

        http_response_code(200);
        exit(json_encode($repair_result));
    }

    # Defensive: ein Whitelist-Eintrag verweist auf einen nicht
    # implementierten Handler. Sollte nicht passieren, ist aber besser
    # zu loggen als still 500 zu werfen.
    app::error_log(
        "setup.php repair id=" . $raw_repair_id
        . " has no handler implementation: " . $handler_name
    );
    http_response_code(500);
    exit(json_encode([
        "ok"      => false,
        "status"  => "no_handler",
        "message" => "Kein Handler fuer Repair-ID registriert.",
    ]));
}

# --- Header-Pfad-Label -------------------------------------------------------
# Setup-View hat kein `?path=...`-Konzept (es wird nichts aus pm/ geladen).
# Das Pfad-Label bleibt fuer die Setup-Seite generell leer.

$header_path_label = "";

# Repo-Root-Label analog zu plan.php / info.php / index.php: parent von
# app/. realpath() schlaegt nur fehl, wenn app/ vom Disk verschwunden ist
# — dann faellt das Label auf "" zurueck, statt die Seite zu killen.

$speckig_root_abs  = realpath(__DIR__ . "/..");
$header_root_label = $speckig_root_abs !== false ? basename($speckig_root_abs) : "";

# --- Checks ausfuehren -------------------------------------------------------
# `setup_checks::run()` faengt einzelne Check-Exceptions selbst ab; ein
# Crash hier waere ein Bug in run() selbst und soll dann auch sichtbar
# kippen, statt still zu schweigen.

$check_results = setup_checks::run();

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>speckig — setup / repair</title>
    <link rel="stylesheet" href="/_share/css/app.css">
</head>
<body>
<?= header::render("setup", $header_path_label, $header_root_label) ?>
<main>
    <nav></nav>
    <article id="content">
        <table class="setup-checks">
            <thead>
                <tr>
                    <th scope="col">Status</th>
                    <th scope="col">Check</th>
                    <th scope="col">Hint</th>
                    <th scope="col">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($check_results as $check_result): ?>
                <?php
                    $status_class      = "status-" . $check_result["status"];
                    $check_can_repair  = ! empty($check_result["can_repair"])
                        && isset($check_result["repair_action"])
                        && $check_result["repair_action"] !== "";
                ?>
                <tr>
                    <td class="<?= app::escape($status_class) ?>"><?= app::escape($check_result["status"]) ?></td>
                    <td><?= app::escape($check_result["name"]) ?></td>
                    <td><?= app::escape($check_result["hint"]) ?></td>
                    <td>
                        <?php if ($check_can_repair): ?>
                            <button type="button" data-repair-id="<?= app::escape($check_result["repair_action"]) ?>">Repair</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</main>
<script src="/_share/js/setup_loader.js"></script>
</body>
</html>
