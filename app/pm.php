<?php

declare(strict_types=1);

// @spec
// JSON-Endpoint fuer den Plan-View AJAX-Loader (M006/0003).
// Liefert eine `.md`-Datei aus dem `pm/`-Tree als gerendertes HTML.
//
// Eingabe: `?path=pm/...` (relativ zum Repo-Root, MUSS mit `pm/` beginnen).
// Erfolg : HTTP 200 + { ok: true, path, html, status }
// Fehler : HTTP 400 + { ok: false, message }
//
// `status`-Ableitung:
//   - Tickets unter `pm/.../open/...` -> "open"
//   - Tickets unter `pm/.../archive/...` -> "done"
//   - `milestone.md`-Dateien -> Wert der `Status:`-Zeile (via pm_reader)
//   - sonst leerer String
//
// Pfad-Traversal-Schutz (mehrschichtig):
//   1) `?path` darf kein `..` enthalten und nicht mit `/` beginnen.
//   2) `?path` MUSS mit `pm/` beginnen.
//   3) Endung MUSS `.md` sein.
//   4) realpath muss innerhalb des Repo-Roots liegen.
//   5) is_file muss true sein.
//
// Bewusste Entscheidung: `data.html` ist Parsedown-Output von Markdown
// aus `pm/`-Dateien (Repo-kontrollierter Inhalt). Markdown-XSS-Hardening
// ist out of scope; fuer V1 vertrauen wir den Repo-Markdown-Quellen.
// @end-spec

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/_share/vendor/Parsedown.php";
require_once $_SERVER["DOCUMENT_ROOT"] . "/_share/pm_reader.php";

use _share\app;
use _share\pm_reader;

# --- Response immer JSON ----------------------------------------------------

header("Content-Type: application/json");

# --- Repo-Root resolven -----------------------------------------------------
# `app/pm.php` liegt unter `app/` — Repo-Root ist parent davon.

$repo_root_abs = realpath(__DIR__ . "/..");

# --- ?path validieren -------------------------------------------------------

$raw_path           = isset($_GET["path"]) ? (string) $_GET["path"] : "";
$path_was_requested = $raw_path !== "";

$path_string_is_safe =
    $path_was_requested
    && ! str_contains($raw_path, "..")
    && $raw_path[0] !== "/"
    && str_starts_with($raw_path, "pm/");

# Endung muss `.md` sein — wir rendern hier ausschliesslich Markdown.
$path_extension     = $path_string_is_safe
    ? strtolower((string) pathinfo($raw_path, PATHINFO_EXTENSION))
    : "";
$path_is_markdown   = $path_extension === "md";

$resolved_path_abs = false;

if ($path_string_is_safe && $path_is_markdown && $repo_root_abs !== false)
{
    $resolved_path_abs = realpath($repo_root_abs . "/" . $raw_path);
}

$path_is_inside_root =
    $resolved_path_abs !== false
    && $repo_root_abs !== false
    && str_starts_with($resolved_path_abs, $repo_root_abs . DIRECTORY_SEPARATOR);

$path_points_to_file =
    $path_is_inside_root
    && is_file($resolved_path_abs);

$path_is_valid =
    $path_string_is_safe
    && $path_is_markdown
    && $path_is_inside_root
    && $path_points_to_file;

if (! $path_is_valid)
{
    app::error_log("pm.php rejected path: " . $raw_path);
    http_response_code(400);
    exit(json_encode([
        "ok"      => false,
        "message" => "Ungueltiger Pfad.",
    ]));
}

# --- Inhalt laden + rendern -------------------------------------------------
# pm_reader::read_markdown() macht eine zweite Schicht Pfad-Schutz und liefert
# bei Verstoss "" zurueck. Doppelte Validierung ist erlaubt, der Vertrag
# nach oben bleibt klar (HTTP 400 bei leerem Content waere ueberraschend,
# wir sind aber an dieser Stelle schon validiert — read_markdown sollte
# liefern. Falls nicht, geben wir 400 zurueck als defensive Notbremse.)

$raw_file_contents = pm_reader::read_markdown($raw_path);

if ($raw_file_contents === "")
{
    app::error_log("pm.php: pm_reader returned empty for " . $raw_path);
    http_response_code(400);
    exit(json_encode([
        "ok"      => false,
        "message" => "Datei leer oder unlesbar.",
    ]));
}

$parsedown_instance = new Parsedown();
$rendered_html      = $parsedown_instance->text($raw_file_contents);

# --- Status ableiten --------------------------------------------------------
# Tickets: aus Pfad. Milestones (`milestone.md`): aus Status:-Zeile.
# Sonst leer.

$path_basename       = basename($raw_path);
$file_is_milestone   = $path_basename === "milestone.md";
$path_is_under_open  = str_contains($raw_path, "/open/");
$path_is_under_archive = str_contains($raw_path, "/archive/");

$derived_status = "";

if ($file_is_milestone)
{
    # Milestones tragen ihren Status im Frontmatter-aehnlichen Block;
    # wir lesen die `Status:`-Zeile direkt aus dem schon geladenen Inhalt
    # via pm_reader-naher Logik (zeilenweise, kein Regex).
    $lines = explode("\n", $raw_file_contents);

    foreach ($lines as $line)
    {
        $line_is_status = str_starts_with($line, "Status:");

        if ($line_is_status)
        {
            $derived_status = trim(substr($line, strlen("Status:")));
            break;
        }
    }
}
else if ($path_is_under_open)
{
    $derived_status = "open";
}
else if ($path_is_under_archive)
{
    $derived_status = "done";
}

# --- success response -------------------------------------------------------

exit(json_encode([
    "ok"     => true,
    "path"   => $raw_path,
    "html"   => $rendered_html,
    "status" => $derived_status,
]));
