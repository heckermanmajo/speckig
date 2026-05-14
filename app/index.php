<?php

declare(strict_types=1);

use _share\app;
use _share\html\header;

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

# Speckig-Layout (seit Ticket 002/0006):
# - Header oben, Repo-Tree links, Datei-Inhalt rechts.
# - Navigation per Tree-Link laeuft via AJAX gegen /file.php (siehe 002/0005).
# - index.php rendert rechts initial nur den Hinweis-Platzhalter; JS uebernimmt
#   beim DOMContentLoaded auch den Fall, dass ?path in der URL steht
#   (content_loader.js, replaceState + load_path).
# - Markdown-/Plaintext-Render lebt jetzt einzig in app/file.php.
# - Pfad-Traversal (.., absolute Pfade, ausserhalb des Roots) wird hart abgewiesen
#   (fuer das Header-Label hier, fuer den Render-Vertrag in file.php).
#
# Login-/document::head()-Nav bleibt absichtlich aussen vor (vgl. 0005).
#
# Repo-Root via SPECKIG_ROOT (Ticket 002/0002):
# Das Speckig-eigene app/ liegt unter $_SERVER["DOCUMENT_ROOT"] und ist
# unabhaengig vom gebrowsten Root. $speckig_root_abs zeigt auf den Repo-Root,
# dessen Inhalt im Tree erscheint. Default = parent von app/ (eigenes Repo).

# --- Speckig-Root resolven --------------------------------------------------

$env_speckig_root  = getenv("SPECKIG_ROOT");
$env_root_is_given = is_string($env_speckig_root) && $env_speckig_root !== "";

if ($env_root_is_given)
{
    $speckig_root_abs = realpath($env_speckig_root);
}
else
{
    $speckig_root_abs = realpath(__DIR__ . "/..");
}

# --- ?path validieren -------------------------------------------------------
# Drei Schichten Traversal-Schutz:
#  1) String-Check: kein "..", kein fuehrender "/".
#  2) Realpath-Check: aufgeloester Pfad muss innerhalb von $speckig_root_abs liegen.
#  3) Existenz: muss eine echte Datei sein.
#
# Wird hier nur noch fuer das Header-Pfad-Label gebraucht; der Render-Pfad lebt
# in /file.php und wird via content_loader.js geholt. Die Validation bleibt drin,
# damit der initiale Server-Render des Headers bei Bookmarks mit ?path nicht kurz
# leer steht (waere haesslich); die Duplikation zur Validation in file.php ist
# bewusst, ein Helper-Refactor ist ein eigenes Ticket (Hinweis in 0004 Done).

$raw_path           = isset($_GET["path"]) ? (string) $_GET["path"] : "";
$path_was_requested = $raw_path !== "";

$path_string_is_safe =
    $path_was_requested
    && ! str_contains($raw_path, "..")
    && $raw_path[0] !== "/";

$resolved_path_abs = false;

if ($path_string_is_safe && $speckig_root_abs !== false)
{
    $resolved_path_abs = realpath($speckig_root_abs . "/" . $raw_path);
}

$path_is_inside_root =
    $resolved_path_abs !== false
    && $speckig_root_abs !== false
    && str_starts_with($resolved_path_abs, $speckig_root_abs . DIRECTORY_SEPARATOR);

$path_points_to_file =
    $path_is_inside_root
    && is_file($resolved_path_abs);

$path_is_valid =
    $path_string_is_safe
    && $path_is_inside_root
    && $path_points_to_file;

if ($path_was_requested && ! $path_is_valid)
{
    app::error_log("index.php rejected path: " . $raw_path);
}

# --- Tree rekursiv aufbauen --------------------------------------------------
# render_tree() liest ein Verzeichnis und gibt verschachtelte
# <details><summary>...</summary>...<a>...</a></details>-Bloecke zurueck.
# Sortierung: Ordner zuerst, dann Dateien, jeweils alphabetisch.
# Versteckte Eintraege (Punkt-Prefix) werden uebersprungen.

function count_visible_children(string $dir_abs): int
{
    $entries = @scandir($dir_abs);

    if ($entries === false)
    {
        return 0;
    }

    $visible_count = 0;

    foreach ($entries as $entry_name)
    {
        $entry_is_hidden = $entry_name === "" || $entry_name[0] === ".";

        if ($entry_is_hidden)
        {
            continue;
        }

        $visible_count++;
    }

    return $visible_count;
}

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
        $sub_dir_abs         = $dir_abs . "/" . $sub_dir_name;
        $sub_dir_rel_prefix  = $rel_prefix . $sub_dir_name . "/";
        $sub_dir_rel_path    = $rel_prefix . $sub_dir_name;
        $visible_child_count = count_visible_children($sub_dir_abs);

        $rendered_html .= "<details data-path=\"" . \_share\app::escape($sub_dir_rel_path) . "\">";
        $rendered_html .= "<summary>";
        $rendered_html .= \_share\app::escape($sub_dir_name) . "/";
        $rendered_html .= " <span style=\"color:#888\">(" . $visible_child_count . ")</span>";
        $rendered_html .= "</summary>";

        # "+ Datei"-Aktion pro Sub-Dir (M013/0004). Heuristik per
        # rel_path-Prefix — Schwarzliste lebt JETZT dreifach inline
        # (file.php Save, file.php new_file, hier). Konsolidierung in
        # einen Helper ist Folge-Ticket, bewusst inline gehalten.
        $dir_is_blacklisted =
            str_starts_with($sub_dir_rel_path, "app/_share/vendor")
            || str_starts_with($sub_dir_rel_path, ".git")
            || str_starts_with($sub_dir_rel_path, "app/_share/spec_parser");

        if (! $dir_is_blacklisted)
        {
            $escaped_dir = \_share\app::escape($sub_dir_rel_path);
            $rendered_html .= "<div class=\"tree-action-block\">";
            $rendered_html .= "<button type=\"button\" class=\"btn-new-file\" data-dir=\"" . $escaped_dir . "\">+ Datei</button>";
            $rendered_html .= "<form class=\"new-file-form\" data-dir=\"" . $escaped_dir . "\" hidden>";
            $rendered_html .= "<input type=\"text\" name=\"name\" class=\"input-name\" placeholder=\"Dateiname\" maxlength=\"120\" required>";
            $rendered_html .= "<button type=\"submit\" class=\"btn-submit\">Anlegen</button>";
            $rendered_html .= "<button type=\"button\" class=\"btn-cancel-form\">Abbrechen</button>";
            $rendered_html .= "<span class=\"form-error\" hidden></span>";
            $rendered_html .= "</form>";
            $rendered_html .= "</div>";
        }

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

if ($speckig_root_abs !== false)
{
    $rendered_tree_html = render_tree($speckig_root_abs, "");
}

# --- Header-Pfadanzeige ------------------------------------------------------
# Zeigt nur den validierten Pfad, nie den $raw_path — sonst Reflected-XSS-Risiko.

$header_path_label = $path_is_valid ? $raw_path : "";
$header_root_label = $speckig_root_abs !== false ? basename($speckig_root_abs) : "";

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>speckig</title>
    <link rel="stylesheet" href="/_share/css/app.css">
    <link rel="stylesheet" href="/_share/vendor/css/codemirror.css">
</head>
<body>
<?= header::render("files", $header_path_label, $header_root_label) ?>
<main>
<?php
# Root-Level "+ Datei"-Aktion (M013/0004). data-dir="" steht fuer den
# Repo-Root, damit Submit eine Datei direkt unter $speckig_root_abs anlegt.
# Auch das Repo-Root soll editierbar sein, daher hier ausserhalb von
# render_tree() platziert (render_tree rendert nur Sub-Dirs).
$root_action_block_html  = "<div class=\"tree-action-block\">";
$root_action_block_html .= "<button type=\"button\" class=\"btn-new-file\" data-dir=\"\">+ Datei (Root)</button>";
$root_action_block_html .= "<form class=\"new-file-form\" data-dir=\"\" hidden>";
$root_action_block_html .= "<input type=\"text\" name=\"name\" class=\"input-name\" placeholder=\"Dateiname\" maxlength=\"120\" required>";
$root_action_block_html .= "<button type=\"submit\" class=\"btn-submit\">Anlegen</button>";
$root_action_block_html .= "<button type=\"button\" class=\"btn-cancel-form\">Abbrechen</button>";
$root_action_block_html .= "<span class=\"form-error\" hidden></span>";
$root_action_block_html .= "</form>";
$root_action_block_html .= "</div>";
?>
    <nav><?= $root_action_block_html ?><?= $rendered_tree_html ?></nav>
    <article id="content"><p>Datei links auswählen.</p></article>
</main>
<script src="/_share/js/helpers.js"></script>
<script src="/_share/js/tree_collapse.js"></script>
<script src="/_share/vendor/js/codemirror.min.js"></script>
<script src="/_share/vendor/js/codemirror-modes/markdown.js"></script>
<script src="/_share/vendor/js/codemirror-modes/xml.js"></script>
<script src="/_share/vendor/js/codemirror-modes/javascript.js"></script>
<script src="/_share/vendor/js/codemirror-modes/css.js"></script>
<script src="/_share/vendor/js/codemirror-modes/clike.js"></script>
<script src="/_share/vendor/js/codemirror-modes/htmlmixed.js"></script>
<script src="/_share/vendor/js/codemirror-modes/php.js"></script>
<script src="/_share/vendor/js/codemirror-modes/shell.js"></script>
<script src="/_share/vendor/js/codemirror-modes/yaml.js"></script>
<script src="/_share/js/editor.js"></script>
<script src="/_share/js/content_loader.js"></script>
</body>
</html>
