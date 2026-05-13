<?php

declare(strict_types=1);

use _share\app;

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/_share/vendor/Parsedown.php";

# Speckig-Layout for ticket 0006:
# - Header oben, pm/-Tree links, Datei-Inhalt rechts.
# - Navigation per ?path=... mit Full-Page-Reload, kein JS.
# - .md -> via vendored Parsedown gerendert. Andere Endungen als <pre>-Plaintext.
# - Pfad-Traversal (.., absolute Pfade, ausserhalb pm/) wird hart abgewiesen.
#
# Login-/document::head()-Nav bleibt absichtlich aussen vor (vgl. 0005).

# --- pm-Root resolven --------------------------------------------------------
# $_SERVER["DOCUMENT_ROOT"] zeigt auf app/. pm/ liegt im Repo-Root daneben.

$doc_root_abs   = $_SERVER["DOCUMENT_ROOT"];
$repo_root_abs  = realpath($doc_root_abs . "/..");
$pm_root_abs    = realpath($repo_root_abs . "/pm");

# --- ?path validieren -------------------------------------------------------
# Drei Schichten Traversal-Schutz:
#  1) String-Check: muss mit "pm/" anfangen, kein "..", kein fuehrender "/".
#  2) Realpath-Check: aufgeloester Pfad muss innerhalb von $pm_root_abs liegen.
#  3) Existenz: muss eine echte Datei sein.

$raw_path             = isset($_GET["path"]) ? (string) $_GET["path"] : "";
$path_was_requested   = $raw_path !== "";

$path_string_is_safe =
    $path_was_requested
    && str_starts_with($raw_path, "pm/")
    && ! str_contains($raw_path, "..")
    && $raw_path[0] !== "/";

$resolved_path_abs = false;

if ($path_string_is_safe)
{
    $resolved_path_abs = realpath($repo_root_abs . "/" . $raw_path);
}

$path_is_inside_pm =
    $resolved_path_abs !== false
    && $pm_root_abs !== false
    && str_starts_with($resolved_path_abs, $pm_root_abs . DIRECTORY_SEPARATOR);

$path_points_to_file =
    $path_is_inside_pm
    && is_file($resolved_path_abs);

$path_is_valid =
    $path_string_is_safe
    && $path_is_inside_pm
    && $path_points_to_file;

if ($path_was_requested && ! $path_is_valid)
{
    app::error_log("index.php rejected path: " . $raw_path);
}

# --- Tree rekursiv aufbauen --------------------------------------------------
# render_tree() liest ein Verzeichnis und gibt verschachtelte
# <details open><summary>...</summary>...<a>...</a></details>-Bloecke zurueck.
# Sortierung: Ordner zuerst, dann Dateien, jeweils alphabetisch.
# Versteckte Eintraege (Punkt-Prefix) werden uebersprungen.

function render_tree(string $dir_abs, string $rel_prefix): string
{
    $entries = @scandir($dir_abs);

    if ($entries === false)
    {
        return "";
    }

    $directory_names = [];
    $file_names      = [];

    foreach ($entries as $entry_name)
    {
        $entry_is_hidden = $entry_name === "" || $entry_name[0] === ".";

        if ($entry_is_hidden)
        {
            continue;
        }

        $entry_abs = $dir_abs . "/" . $entry_name;

        if (is_dir($entry_abs))
        {
            $directory_names[] = $entry_name;
        }
        elseif (is_file($entry_abs))
        {
            $file_names[] = $entry_name;
        }
    }

    sort($directory_names, SORT_STRING);
    sort($file_names, SORT_STRING);

    $rendered_html = "";

    foreach ($directory_names as $sub_dir_name)
    {
        $sub_dir_abs        = $dir_abs . "/" . $sub_dir_name;
        $sub_dir_rel_prefix = $rel_prefix . $sub_dir_name . "/";

        $rendered_html .= "<details open>";
        $rendered_html .= "<summary>" . \_share\app::escape($sub_dir_name) . "/</summary>";
        $rendered_html .= render_tree($sub_dir_abs, $sub_dir_rel_prefix);
        $rendered_html .= "</details>";
    }

    foreach ($file_names as $leaf_file_name)
    {
        $file_rel_path  = $rel_prefix . $leaf_file_name;
        $href_attribute = "/?path=" . \_share\app::escape($file_rel_path);

        $rendered_html .= "<a href=\"" . $href_attribute . "\">";
        $rendered_html .= \_share\app::escape($leaf_file_name);
        $rendered_html .= "</a>";
    }

    return $rendered_html;
}

$rendered_tree_html = "";

if ($pm_root_abs !== false)
{
    $rendered_tree_html = render_tree($pm_root_abs, "pm/");
}

# --- Datei-Content rendern ---------------------------------------------------
# .md -> Parsedown. Sonst -> <pre>-Plaintext mit escape.

$rendered_content_html = "<p>Datei links auswählen.</p>";

if ($path_was_requested && ! $path_is_valid)
{
    $rendered_content_html = "<p>Ungültiger Pfad.</p>";
}

if ($path_is_valid)
{
    $raw_file_contents = (string) file_get_contents($resolved_path_abs);
    $file_extension    = strtolower(pathinfo($resolved_path_abs, PATHINFO_EXTENSION));
    $file_is_markdown  = $file_extension === "md";

    if ($file_is_markdown)
    {
        $parsedown_instance     = new Parsedown();
        $rendered_content_html  = $parsedown_instance->text($raw_file_contents);
    }
    else
    {
        $rendered_content_html = "<pre>" . app::escape($raw_file_contents) . "</pre>";
    }
}

# --- Header-Pfadanzeige ------------------------------------------------------
# Zeigt nur den validierten Pfad, nie den $raw_path — sonst Reflected-XSS-Risiko.

$header_path_label = $path_is_valid ? $raw_path : "";

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>speckig</title>
    <style>
        body { margin: 0; font-family: system-ui, sans-serif; }
        header { padding: 0.5rem 1rem; border-bottom: 1px solid #ccc; }
        main { display: grid; grid-template-columns: 1fr 1fr; height: calc(100vh - 3rem); }
        nav, article { padding: 1rem; overflow: auto; }
        nav { border-right: 1px solid #ccc; }
        nav details > details, nav details > a { margin-left: 1.25rem; display: block; }
        nav summary { cursor: default; }
        nav a { text-decoration: none; }
        nav a:hover { text-decoration: underline; }
        article pre { white-space: pre-wrap; word-wrap: break-word; }
    </style>
</head>
<body>
<header>
    <strong>speckig</strong>
    <?php if ($header_path_label !== "") { ?>
        — <?= app::escape($header_path_label) ?>
    <?php } ?>
</header>
<main>
    <nav><?= $rendered_tree_html ?></nav>
    <article><?= $rendered_content_html ?></article>
</main>
</body>
</html>
