<?php

declare(strict_types=1);

// @spec
// Setup/Repair-View (M015/0001): vierte Hauptansicht des Speckig-UI.
// Diese Seite ist bewusst so robust gebaut, dass sie auch dann etwas
// Sinnvolles anzeigen kann, wenn die Welt drumherum kaputt ist (kein
// SPECKIG_ROOT, fehlende how-to-Files, …) — sie ist genau fuer diesen
// Fall da.
//
// Linker Bereich (`<nav>`): in 0001 leer. Die Sidebar (Liste der
// Self-Checks / Repair-Buttons) kommt in den naechsten Tickets
// (0002/0003/0004).
// Rechter Bereich (`<article id="content">`): in 0001 nur ein
// Placeholder-Hinweis, dass der eigentliche Inhalt noch folgt.
//
// Header: `_share\html\header::render("setup", ...)` — vierter Tab
// nach Files, Plan, Info. Stylesheet: `app/_share/css/app.css`
// (geteilt mit Tree-, Plan- und Info-View).
// @end-spec

use _share\app;
use _share\html\header;

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
        <p><?= app::escape("Setup/Repair-Inhalt folgt.") ?></p>
    </article>
</main>
</body>
</html>
