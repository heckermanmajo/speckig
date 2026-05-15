<?php

declare(strict_types=1);

// @spec
// Setup/Repair-View (M015/0001, ausgebaut in 0002).
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
//   - Action-Spalte ist in 0002 leer; Repair-Buttons kommen in 0004.
//
// Header: `_share\html\header::render("setup", ...)` — vierter Tab nach
// Files, Plan, Info. Stylesheet: `app/_share/css/app.css` (geteilt mit
// Tree-, Plan- und Info-View).
// @end-spec

use _share\app;
use _share\html\header;
use _share\setup_checks;

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

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
                    $status_class = "status-" . $check_result["status"];
                ?>
                <tr>
                    <td class="<?= app::escape($status_class) ?>"><?= app::escape($check_result["status"]) ?></td>
                    <td><?= app::escape($check_result["name"]) ?></td>
                    <td><?= app::escape($check_result["hint"]) ?></td>
                    <td><!-- repair-buttons kommen in M015/0004 --></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</main>
</body>
</html>
