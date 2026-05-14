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
require_once $_SERVER["DOCUMENT_ROOT"] . "/_share/spec_parser/spec_parser.php";

use _share\app;
use _share\spec_parser\spec_parser;

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

# --- spec-view payload (M005/0005) ------------------------------------------
# Fuer .php/.js-Dateien: Parser-Output anhaengen, damit das Frontend ueber
# dem Code eine Spec-View rendern kann. Andere Endungen liefern null.
# Pfad an den Parser: bevorzugt repo-relativ (fuer "file"-Anzeige im Schema),
# Fallback ist der absolute Pfad. Vendor- und unsupported-Dateien geben einen
# Error im Schema zurueck — wir reichen Errors trotzdem durch (das Frontend
# darf entscheiden, sie nicht zu zeigen). Leere Schemata ohne file_spec UND
# ohne symbols UND ohne error werden auf null kondensiert: kein Mehrwert,
# keine ueberfluessige Spec-View im UI.

# Closure (selbst-referenziell via use(&$...)): rekursive Suche nach
# mind. einer spec-Zeile in den Symbolen oder ihren Members. Damit erkennen
# wir Dateien wie db.php, deren Top-Level-Klasse zwar im Schema auftaucht,
# aber keine @spec-Marker traegt — solche Dateien sollen kein Spec-View
# bekommen ("kein Mehrwert"-Filter).
$spec_walker = function (array $symbol_list) use (&$spec_walker): bool
{
    foreach ($symbol_list as $symbol)
    {
        $symbol_has_spec_lines =
            isset($symbol["spec"])
            && is_array($symbol["spec"])
            && count($symbol["spec"]) > 0;

        if ($symbol_has_spec_lines)
        {
            return true;
        }

        $members_have_spec =
            isset($symbol["members"])
            && is_array($symbol["members"])
            && $spec_walker($symbol["members"]);

        if ($members_have_spec)
        {
            return true;
        }
    }

    return false;
};

$spec_payload   = null;
$language_is_supported_for_spec =
    $file_extension === "php"
    || $file_extension === "js";

if ($language_is_supported_for_spec)
{
    # Absoluter Pfad: spec_parser nutzt is_file() ohne CWD-Vorsicht, also
    # MUSS hier absolut rein. Der Vendor-Blacklist-Check matcht textuell
    # auf "app/_share/vendor/" — das Substring steht auch im absoluten
    # Pfad, also weiter korrekt. Das "file"-Feld im Schema enthaelt dann
    # zwar den absoluten Pfad; das Frontend rendert es ohnehin nicht.
    $parser_result = spec_parser::parse($resolved_path_abs);

    $parser_returned_error = isset($parser_result["error"]);

    # Wir reichen Errors NICHT durch (vendor / unsupported language).
    # Vendor-Pfade werden vom Browser eh selten angefragt; wenn doch,
    # ist der Banner kein Mehrwert.
    if ($parser_returned_error)
    {
        $spec_payload = null;
    }
    else
    {
        # Filter: nur eine Spec-View rendern, wenn die Datei wirklich
        # mind. eine @spec-Zeile traegt. Datei-Spec ODER irgendein Symbol /
        # Member traegt eine spec-Zeile.
        $schema_has_any_spec_content =
            ! empty($parser_result["file_spec"])
            || $spec_walker($parser_result["symbols"] ?? []);

        if ($schema_has_any_spec_content)
        {
            $spec_payload = $parser_result;
        }
        else
        {
            $spec_payload = null;
        }
    }
}

# --- success response -------------------------------------------------------

exit(json_encode([
    "ok"   => true,
    "path" => $raw_path,
    "html" => $rendered_html,
    "spec" => $spec_payload,
]));
