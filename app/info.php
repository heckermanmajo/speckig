<?php

declare(strict_types=1);

// @spec
// Info-View (M011/0002): dritte Hauptansicht des Speckig-UI.
// Linker Bereich (`<nav>`): Sidebar mit den fuenf Info-Sektionen Ideas,
// Reports, Decisions, Audits, Terms — jeweils ein ausklappbarer
// `<details data-path="pm/<name>">`-Block. Read-only. Klicks auf
// Eintraege laden die Datei AJAX-basiert via `/pm.php?path=...` in den
// rechten Bereich (siehe `plan_loader.js`, der auf
// `a.plan-ticket-link` reagiert und die aktuelle Route aus
// `window.location.pathname` als Push-Basis nimmt).
// Rechter Bereich (`<article id="content">`): initial Platzhalter
// "Eintrag links auswaehlen.".
//
// Datenquelle: `_share\pm_reader::list_info_sections()` (in M011/0001
// angelegt). Plan-Sidebar enthaelt diese Sektionen seit M011/0002 nicht
// mehr.
// Header: `_share\html\header::render("info", ...)` — dritter Tab nach
// Files und Plan. Stylesheet: `app/_share/css/app.css` (geteilt mit
// Tree- und Plan-View).
// XSS: alle aus `pm_reader` stammenden Strings (slugs, titles, paths)
// werden via `_share\app::escape()` escaped, bevor sie ins HTML wandern.
// @end-spec

use _share\app;
use _share\html\header;
use _share\pm_reader;

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

# --- Sidebar-Daten holen -----------------------------------------------------
# Read-only: pm_reader liefert die fertige Struktur, kein Filtern noetig.

$info_sections = pm_reader::list_info_sections();

# --- Header-Pfad-Label -------------------------------------------------------
# `?path=...` ist heute nur "durchgereicht": fuer den Header zeigen wir den
# rohen Wert nicht an, wenn er den pm/-Vertrag verletzt — sonst Reflected-XSS-
# Risiko via Header-Label. Der eigentliche Markdown-Render laeuft via AJAX
# gegen `pm.php`; `?path` dient hier nur als Header-Hint fuer Bookmarks.

$raw_path           = isset($_GET["path"]) ? (string) $_GET["path"] : "";
$path_was_requested = $raw_path !== "";

$path_string_is_safe =
    $path_was_requested
    && ! str_contains($raw_path, "..")
    && $raw_path[0] !== "/"
    && str_starts_with($raw_path, "pm/");

$header_path_label = $path_string_is_safe ? $raw_path : "";

# Repo-Root-Label analog zu plan.php / index.php: parent von app/.
$speckig_root_abs  = realpath(__DIR__ . "/..");
$header_root_label = $speckig_root_abs !== false ? basename($speckig_root_abs) : "";

# --- Sidebar-Renderer --------------------------------------------------------

// @spec
// Rendert eine Info-Sektion (Ideas/Reports/Decisions/Audits/Terms) als
// `<details class="plan-bugs">`-Block.
// $heading: Summary-Text ("Ideas", "Reports", ...).
// $entries: Array von `{slug, path, title}` aus pm_reader::list_info_sections().
// $section_data_path: Wert fuer `data-path` (z.B. "pm/ideas") — fuer
//   tree_collapse.js, das den Open/Closed-Zustand persistiert.
// $action_block_html: optionaler HTML-Block, der INNERHALB des
//   `.plan-milestone-body` unter der Liste eingefuegt wird (M014/0003 nutzt
//   das fuer den "+ Idee"-Button + die `.new-idea-form`). Default "" hat
//   keinen Effekt — die anderen Sektionen bekommen weiterhin nichts.
// Bei leerer Liste wird `<p class="plan-tickets-empty">keine</p>` gerendert,
// analog zu render_bugs_block in plan.php. Klick-Targets nutzen
// `plan-ticket-link`, damit plan_loader.js ohne Aenderung greift, und
// `href="/info.php?path=..."`, damit der Loader (der
// `window.location.pathname` als Push-Basis nimmt) auf der Info-Route
// bleibt.
// Strukturell sehr nah an render_bugs_block in plan.php — bewusst
// dupliziert statt abstrahiert (Don't add abstractions beyond what the
// task requires).
// @end-spec
function render_info_section(string $heading, array $entries, string $section_data_path, string $action_block_html = ""): string
{
    $rendered = "<details class=\"plan-bugs\""
        . " data-path=\"" . app::escape($section_data_path) . "\">";

    $rendered .= "<summary class=\"plan-milestone-summary\">" . app::escape($heading) . "</summary>";
    $rendered .= "<div class=\"plan-milestone-body\">";

    $entries_are_empty = count($entries) === 0;

    if ($entries_are_empty)
    {
        $rendered .= "<p class=\"plan-tickets-empty\">keine</p>";
    }
    else
    {
        $rendered .= "<ul class=\"plan-tickets\">";

        foreach ($entries as $entry)
        {
            $entry_href = "/info.php?path=" . app::escape($entry["path"]);

            $rendered .= "<li>";
            $rendered .= "<a href=\"" . $entry_href . "\" class=\"plan-ticket-link\">";
            $rendered .= app::escape($entry["slug"]);

            if ($entry["title"] !== "")
            {
                $rendered .= " — " . app::escape($entry["title"]);
            }

            $rendered .= "</a>";
            $rendered .= "</li>";
        }

        $rendered .= "</ul>";
    }

    # Optionaler Action-Block (M014/0003): "+ Idee"-Button + verstecktes
    # Inline-Formular. Wird nur fuer die Ideas-Sektion uebergeben, alle
    # anderen Sektionen rufen render_info_section ohne dieses Argument auf.
    $action_block_is_present = $action_block_html !== "";

    if ($action_block_is_present)
    {
        $rendered .= $action_block_html;
    }

    $rendered .= "</div>";
    $rendered .= "</details>";

    return $rendered;
}

# --- Sidebar zusammenbauen ---------------------------------------------------

$sidebar_html = "";

# Action-Block fuer die Ideas-Sektion (M014/0003): "+ Idee"-Button plus
# verstecktes Inline-Formular mit Slug-Eingabe. Submit-Logik lebt in
# plan_loader.js (init_new_idea_form), Endpoint `POST /pm.php?action=new_idea`.
# Markup-Stil spiegelt die `.new-milestone-form` / `.new-ticket-form` aus
# plan.php — gleiche Klassen (`.btn-cancel-form`, `.form-error`, `.input-slug`),
# damit die existierenden Helper greifen koennen.
$ideas_action_block_html  = "<div class=\"plan-action-block\">";
$ideas_action_block_html .= "<button type=\"button\" class=\"btn-new-idea\">+ Idee</button>";
$ideas_action_block_html .= "<form class=\"new-idea-form\" hidden>";
$ideas_action_block_html .= "<input type=\"text\" name=\"slug\" class=\"input-slug\" placeholder=\"slug (a-z, -)\" maxlength=\"80\" required>";
$ideas_action_block_html .= "<button type=\"submit\" class=\"btn-submit\">Anlegen</button>";
$ideas_action_block_html .= "<button type=\"button\" class=\"btn-cancel-form\">Abbrechen</button>";
$ideas_action_block_html .= "<span class=\"form-error\" hidden></span>";
$ideas_action_block_html .= "</form>";
$ideas_action_block_html .= "</div>";

$sidebar_html .= "<h2 class=\"plan-section-heading\">Info</h2>";
$sidebar_html .= render_info_section("Ideas",     $info_sections["ideas"],     "pm/ideas",     $ideas_action_block_html);
$sidebar_html .= render_info_section("Reports",   $info_sections["reports"],   "pm/reports");
$sidebar_html .= render_info_section("Decisions", $info_sections["decisions"], "pm/decisions");
$sidebar_html .= render_info_section("Audits",    $info_sections["audits"],    "pm/audits");
$sidebar_html .= render_info_section("Terms",     $info_sections["terms"],     "pm/terms");

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>speckig — info</title>
    <link rel="stylesheet" href="/_share/css/app.css">
    <link rel="stylesheet" href="/_share/vendor/css/codemirror.css">
</head>
<body>
<?= header::render("info", $header_path_label, $header_root_label) ?>
<main>
    <nav><?= $sidebar_html ?></nav>
    <article id="content"><p>Eintrag links auswaehlen.</p></article>
</main>
<script src="/_share/js/helpers.js"></script>
<script src="/_share/js/tree_collapse.js"></script>
<script src="/_share/vendor/js/codemirror.min.js"></script>
<script src="/_share/vendor/js/codemirror-modes/markdown.js"></script>
<script src="/_share/js/editor.js"></script>
<script src="/_share/js/plan_loader.js"></script>
</body>
</html>
