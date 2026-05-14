<?php

declare(strict_types=1);

// @spec
// Plan-View (M006): zweite Hauptansicht des Speckig-UI.
// Linker Bereich (`<nav>`): Sidebar mit allen Milestones (active oben,
// archived unten) und Bugs (open + archive), jeweils als ausklappbare
// `<details>`-Bloecke. Klicks auf Eintraege fuehren initial via
// `?path=...` zurueck auf `plan.php` (AJAX-Render im rechten Panel
// folgt in M006/0003 — heute Platzhalter).
// Rechter Bereich (`<article id="content">`): in 0002 nur Platzhalter.
//
// Datenquelle: `_share\pm_reader::list_milestones()` und
// `_share\pm_reader::list_bugs()` (read-only, scandir-basiert).
// Header: `_share\html\header::render("plan", ...)` analog zu
// `app/index.php`. Stylesheet: `app/_share/css/app.css` (geteilt mit
// der Tree-View, in 0002 ausgezogen).
// XSS: alle aus `pm_reader` stammenden Strings (slugs, titles, paths,
// status) werden via `_share\app::escape()` escaped, bevor sie ins
// HTML wandern.
// @end-spec

use _share\app;
use _share\html\header;
use _share\pm_reader;

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

# --- Sidebar-Daten holen -----------------------------------------------------
# Read-only: pm_reader liefert die fertige Struktur, kein Filtern noetig.

$milestones = pm_reader::list_milestones();
$bugs       = pm_reader::list_bugs();

# --- Header-Pfad-Label -------------------------------------------------------
# `?path=...` ist heute nur "durchgereicht": fuer den Header zeigen wir den
# rohen Wert nicht an, wenn er den pm/-Vertrag verletzt — sonst Reflected-XSS-
# Risiko via Header-Label. AJAX-Render in 0003 wird `?path` semantisch
# nutzen; in 0002 dient er nur als Platzhalter und Header-Hint.

$raw_path           = isset($_GET["path"]) ? (string) $_GET["path"] : "";
$path_was_requested = $raw_path !== "";

$path_string_is_safe =
    $path_was_requested
    && ! str_contains($raw_path, "..")
    && $raw_path[0] !== "/"
    && str_starts_with($raw_path, "pm/");

$header_path_label = $path_string_is_safe ? $raw_path : "";

# Repo-Root-Label analog zu index.php: parent von app/.
$speckig_root_abs  = realpath(__DIR__ . "/..");
$header_root_label = $speckig_root_abs !== false ? basename($speckig_root_abs) : "";

# --- Sidebar-Renderer --------------------------------------------------------

// @spec
// Rendert die Tickets-Liste eines Milestones (open ODER archive).
// $heading: Ueberschriftstext (z.B. "Open" oder "Done").
// $tickets: Array von `{slug, path, title}` aus pm_reader.
// $extra_link_class: zusaetzliche CSS-Klasse fuer den `<a>`-Tag,
//   z.B. "plan-ticket-done" fuer archivierte Tickets.
// Liefert leeren String, wenn $tickets leer ist (kein leeres `<ul>`).
// Alle dynamischen Strings werden via app::escape() escaped.
// @end-spec
function render_tickets_block(string $heading, array $tickets, string $extra_link_class): string
{
    $tickets_are_empty = count($tickets) === 0;

    if ($tickets_are_empty)
    {
        return "";
    }

    $rendered = "<h4 class=\"plan-tickets-heading\">" . app::escape($heading) . "</h4>";
    $rendered .= "<ul class=\"plan-tickets\">";

    foreach ($tickets as $ticket)
    {
        $ticket_href = "/plan.php?path=" . app::escape($ticket["path"]);

        $link_class = "plan-ticket-link";

        if ($extra_link_class !== "")
        {
            $link_class .= " " . $extra_link_class;
        }

        $rendered .= "<li>";
        $rendered .= "<a href=\"" . $ticket_href . "\" class=\"" . app::escape($link_class) . "\">";
        $rendered .= app::escape($ticket["slug"]);

        if ($ticket["title"] !== "")
        {
            $rendered .= " — " . app::escape($ticket["title"]);
        }

        $rendered .= "</a>";
        $rendered .= "</li>";
    }

    $rendered .= "</ul>";

    return $rendered;
}

// @spec
// Rendert einen Milestone-Block als `<details>`-Element.
// $milestone: Eintrag aus pm_reader::list_milestones() mit Schluesseln
//   `slug`, `path`, `title`, `status`, `tickets_open`, `tickets_archive`.
// `data-path` zeigt auf `<path>/milestone.md`, damit
// `tree_collapse.js` den Open/Closed-Zustand ueber Reloads persistieren
// kann (Schluessel = Pfad).
// Default zugeklappt (kein `open`-Attribut), konsistent zur Tree-View.
// @end-spec
function render_milestone_block(array $milestone): string
{
    $slug   = $milestone["slug"];
    $path   = $milestone["path"];
    $title  = $milestone["title"];
    $status = $milestone["status"];

    $milestone_md_path = $path . "/milestone.md";

    $rendered = "<details class=\"plan-milestone\""
        . " data-slug=\"" . app::escape($slug) . "\""
        . " data-path=\"" . app::escape($milestone_md_path) . "\">";

    $rendered .= "<summary class=\"plan-milestone-summary\">";
    $rendered .= "<code>" . app::escape($slug) . "</code>";

    if ($title !== "")
    {
        $rendered .= " <span class=\"plan-milestone-title\">" . app::escape($title) . "</span>";
    }

    if ($status !== "")
    {
        $rendered .= " <span class=\"plan-milestone-status\">(" . app::escape($status) . ")</span>";
    }

    $rendered .= "</summary>";

    $rendered .= "<div class=\"plan-milestone-body\">";

    $milestone_md_href = "/plan.php?path=" . app::escape($milestone_md_path);
    $rendered .= "<a href=\"" . $milestone_md_href . "\" class=\"plan-milestone-link\">milestone.md</a>";

    $rendered .= render_tickets_block("Open", $milestone["tickets_open"], "");
    $rendered .= render_tickets_block("Done", $milestone["tickets_archive"], "plan-ticket-done");

    $rendered .= "</div>";
    $rendered .= "</details>";

    return $rendered;
}

// @spec
// Rendert einen Bug-Sammelblock als `<details>`.
// $heading: Summary-Text ("Open" / "Archive").
// $bugs: Array von `{slug, path, title}` aus pm_reader::list_bugs().
// $section_data_path: Wert fuer `data-path`, damit tree_collapse.js
//   den Open/Closed-Zustand persistiert (z.B. "pm/bugs/open").
// Bei leerer Liste wird trotzdem ein `<details>` mit `<p
// class="plan-tickets-empty">keine</p>` gerendert — konsistente
// Sichtbarkeit der Sektion, kein Layout-Sprung.
// @end-spec
function render_bugs_block(string $heading, array $bugs, string $section_data_path): string
{
    $rendered = "<details class=\"plan-bugs\""
        . " data-path=\"" . app::escape($section_data_path) . "\">";

    $rendered .= "<summary class=\"plan-milestone-summary\">" . app::escape($heading) . "</summary>";
    $rendered .= "<div class=\"plan-milestone-body\">";

    $bugs_are_empty = count($bugs) === 0;

    if ($bugs_are_empty)
    {
        $rendered .= "<p class=\"plan-tickets-empty\">keine</p>";
    }
    else
    {
        $rendered .= "<ul class=\"plan-tickets\">";

        foreach ($bugs as $bug)
        {
            $bug_href = "/plan.php?path=" . app::escape($bug["path"]);

            $rendered .= "<li>";
            $rendered .= "<a href=\"" . $bug_href . "\" class=\"plan-ticket-link\">";
            $rendered .= app::escape($bug["slug"]);

            if ($bug["title"] !== "")
            {
                $rendered .= " — " . app::escape($bug["title"]);
            }

            $rendered .= "</a>";
            $rendered .= "</li>";
        }

        $rendered .= "</ul>";
    }

    $rendered .= "</div>";
    $rendered .= "</details>";

    return $rendered;
}

# --- Sidebar zusammenbauen ---------------------------------------------------

$sidebar_html = "";

# Milestones-Section
$sidebar_html .= "<h2 class=\"plan-section-heading\">Milestones</h2>";

foreach ($milestones["active"] as $milestone)
{
    $sidebar_html .= render_milestone_block($milestone);
}

$archived_milestones_present = count($milestones["archived"]) > 0;

if ($archived_milestones_present)
{
    $sidebar_html .= "<h3 class=\"plan-section-subheading\">Archived</h3>";

    foreach ($milestones["archived"] as $milestone)
    {
        $sidebar_html .= render_milestone_block($milestone);
    }
}

# Bugs-Section
$sidebar_html .= "<h2 class=\"plan-section-heading\">Bugs</h2>";
$sidebar_html .= render_bugs_block("Open",    $bugs["open"],    "pm/bugs/open");
$sidebar_html .= render_bugs_block("Archive", $bugs["archive"], "pm/bugs/archive");

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>speckig — plan</title>
    <link rel="stylesheet" href="/_share/css/app.css">
</head>
<body>
<?= header::render("plan", $header_path_label, $header_root_label) ?>
<main>
    <nav><?= $sidebar_html ?></nav>
    <article id="content"><p>Milestone oder Ticket links auswaehlen.</p></article>
</main>
<script src="/_share/js/helpers.js"></script>
<script src="/_share/js/tree_collapse.js"></script>
<script src="/_share/js/plan_loader.js"></script>
</body>
</html>
