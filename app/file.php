<?php

declare(strict_types=1);

# JSON-Endpoint fuer den AJAX-Content-Loader (Ticket 002/0004).
# Liefert eine Datei aus dem SPECKIG_ROOT als gerendertes HTML im JSON-Wrapper.
#   .md     -> Parsedown
#   sonst   -> <pre> + app::escape
# Pfad-Traversal-Schutz ist identisch zu index.php — bewusste Duplikation,
# wird in Ticket 002/0006 konsolidiert. Variablen-Namen sind hier absichtlich
# 1:1 zu index.php gehalten, damit der Move einfach bleibt.

# --- bootstrap ---

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/_share/vendor/Parsedown.php";

use _share\app;

# --- Response immer JSON ---

header("Content-Type: application/json");

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
# Drei Schichten Traversal-Schutz (identisch zu index.php):
#  1) String-Check: kein "..", kein fuehrender "/".
#  2) Realpath-Check: aufgeloester Pfad muss innerhalb von $speckig_root_abs liegen.
#  3) Existenz: muss eine echte Datei sein.

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

if (! $path_is_valid)
{
    app::error_log("file.php rejected path: " . $raw_path);
    http_response_code(400);
    exit(json_encode([
        "ok"      => false,
        "message" => "Ungueltiger Pfad.",
    ]));
}

# --- Content laden + rendern ------------------------------------------------

$raw_file_contents  = (string) file_get_contents($resolved_path_abs);
$file_extension     = strtolower(pathinfo($resolved_path_abs, PATHINFO_EXTENSION));
$content_is_markdown = $file_extension === "md";

if ($content_is_markdown)
{
    $parsedown_instance = new Parsedown();
    $rendered_html      = $parsedown_instance->text($raw_file_contents);
}
else
{
    $rendered_html = "<pre>" . app::escape($raw_file_contents) . "</pre>";
}

# --- success response -------------------------------------------------------

exit(json_encode([
    "ok"   => true,
    "path" => $raw_path,
    "html" => $rendered_html,
]));
