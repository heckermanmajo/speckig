<?php

declare(strict_types=1);

// @spec
// JSON-Endpoint fuer den Plan-View AJAX-Loader (M006/0003) plus
// Save-Endpoint fuer den CodeMirror-Editor (M012/0002).
//
// GET  ?path=pm/...                  -> Markdown laden + rendern.
// POST ?action=save&path=pm/....md   -> Body raw in die Datei schreiben.
//
// Erfolg : HTTP 200 + JSON (Form je nach Aktion, siehe unten).
// Fehler : HTTP 400/413/500 + { ok: false, message }.
//
// `status`-Ableitung (GET):
//   - Tickets unter `pm/.../open/...` -> "open"
//   - Tickets unter `pm/.../archive/...` -> "done"
//   - `milestone.md`-Dateien -> Wert der `Status:`-Zeile (via pm_reader)
//   - sonst leerer String
//
// Pfad-Traversal-Schutz (mehrschichtig, identisch fuer GET und POST):
//   1) `path` darf kein `..` enthalten und nicht mit `/` beginnen.
//   2) `path` MUSS mit `pm/` beginnen.
//   3) Endung MUSS `.md` sein.
//   4) GET: realpath muss innerhalb des Repo-Roots liegen + is_file true.
//   5) POST: parent-Verzeichnis muss existieren und innerhalb Repo-Root
//      liegen (neue Files sind erlaubt).
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

# --- Method-Dispatch: POST ?action=save ------------------------------------
# Save kommt VOR dem GET-Pfad, damit der bestehende Read-Pfad unangetastet
# bleibt. Siehe M012/0002.

$method_is_post = $_SERVER["REQUEST_METHOD"] === "POST";
$action         = isset($_GET["action"]) ? (string) $_GET["action"] : "";
$action_is_save = $action === "save";

if ($method_is_post && $action_is_save)
{
    // @spec
    // POST ?action=save&path=pm/....md schreibt den raw Request-Body in
    // die Zieldatei. Vertrag:
    //   - Pfad-Regeln wie GET (`pm/`-Prefix, kein `..`, kein fuehrender
    //     `/`, Endung `.md`).
    //   - `archive/`-Pfade werden defensiv abgelehnt (M012 erlaubt nur
    //     Edit ausserhalb `archive/`).
    //   - Parent-Verzeichnis muss existieren und innerhalb Repo-Root sein;
    //     die Zieldatei selbst darf neu sein.
    //   - Max 1 MB Body, sonst 413.
    //   - Schreiben atomar via tmp+rename.
    //   - Antwort: 200 + {ok:true, path, bytes} bei Erfolg,
    //     400/413/500 + {ok:false, message} bei Fehler.
    //   - Jede Abweisung wird via app::error_log() protokolliert.
    // @end-spec

    $save_raw_path = isset($_GET["path"]) ? (string) $_GET["path"] : "";

    $save_path_was_requested = $save_raw_path !== "";

    $save_path_string_is_safe =
        $save_path_was_requested
        && ! str_contains($save_raw_path, "..")
        && $save_raw_path[0] !== "/"
        && str_starts_with($save_raw_path, "pm/");

    $save_path_extension     = $save_path_string_is_safe
        ? strtolower((string) pathinfo($save_raw_path, PATHINFO_EXTENSION))
        : "";
    $save_path_is_markdown   = $save_path_extension === "md";

    $save_path_is_archive    = str_contains($save_raw_path, "/archive/");

    # `archive/` wird vor jedem weiteren Check abgelehnt — auch wenn
    # alles andere passt. Defensive Schicht, damit ein versehentlich
    # gebauter Save-Call nichts im Archiv anfasst.
    if ($save_path_is_archive)
    {
        app::error_log("pm.php save rejected archive path: " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Pfad.",
        ]));
    }

    $save_basic_path_is_valid =
        $save_path_string_is_safe
        && $save_path_is_markdown
        && $repo_root_abs !== false;

    if (! $save_basic_path_is_valid)
    {
        app::error_log("pm.php save rejected path (basic): " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Pfad.",
        ]));
    }

    # parent-Check: das Verzeichnis muss existieren, und realpath des
    # Parents muss innerhalb Repo-Root liegen. Damit sind neue Files in
    # bestehenden Ordnern erlaubt, ein "Magic Path" auf neue Verzeichnisse
    # aber nicht.
    $parent_rel = dirname($save_raw_path);
    $parent_abs = realpath($repo_root_abs . "/" . $parent_rel);

    $parent_is_inside_root =
        $parent_abs !== false
        && str_starts_with($parent_abs, $repo_root_abs . DIRECTORY_SEPARATOR);

    $parent_is_dir = $parent_abs !== false && is_dir($parent_abs);

    $save_parent_is_valid =
        $parent_is_inside_root
        && $parent_is_dir;

    if (! $save_parent_is_valid)
    {
        app::error_log("pm.php save rejected path (parent): " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Pfad.",
        ]));
    }

    # Body lesen. Wir ignorieren Content-Type — egal ob text/plain oder
    # application/octet-stream, wir nehmen die Bytes wie sie kommen.
    $body = file_get_contents("php://input");

    $body_read_failed = $body === false;

    if ($body_read_failed)
    {
        app::error_log("pm.php save body read failed for: " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $body_is_too_large = strlen($body) > 1048576;

    if ($body_is_too_large)
    {
        app::error_log("pm.php save body too large for: " . $save_raw_path);
        http_response_code(413);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body zu gross.",
        ]));
    }

    $target_abs = $repo_root_abs . "/" . $save_raw_path;
    $tmp_abs    = $target_abs . ".tmp." . bin2hex(random_bytes(4));

    $bytes_written = @file_put_contents($tmp_abs, $body);

    $tmp_write_failed = $bytes_written === false;

    if ($tmp_write_failed)
    {
        app::error_log("pm.php save tmp write failed for: " . $save_raw_path);

        # tmp-cleanup falls die Datei doch teilweise entstanden ist.
        if (is_file($tmp_abs))
        {
            @unlink($tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Schreiben fehlgeschlagen.",
        ]));
    }

    $rename_ok = @rename($tmp_abs, $target_abs);

    if (! $rename_ok)
    {
        app::error_log("pm.php save rename failed for: " . $save_raw_path);

        if (is_file($tmp_abs))
        {
            @unlink($tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Rename fehlgeschlagen.",
        ]));
    }

    exit(json_encode([
        "ok"    => true,
        "path"  => $save_raw_path,
        "bytes" => strlen($body),
    ]));
}

# --- ?path validieren (GET-Pfad) -------------------------------------------

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
