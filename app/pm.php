<?php

declare(strict_types=1);

// @spec
// JSON-Endpoint fuer den Plan-View AJAX-Loader (M006/0003) plus
// Save-Endpoint fuer den CodeMirror-Editor (M012/0002) plus
// new_milestone-Endpoint fuer die "+ Milestone"-Action (M012/0005).
//
// GET  ?path=pm/...                  -> Markdown laden + rendern.
// POST ?action=save&path=pm/....md   -> Body raw in die Datei schreiben.
// POST ?action=new_milestone         -> Neuen Milestone-Folder anlegen.
//                                       Body JSON: {slug, title}.
//                                       Antwort: {ok:true, slug, path}.
//
// Erfolg : HTTP 200 + JSON (Form je nach Aktion, siehe unten).
// Fehler : HTTP 400/409/500 + { ok: false, message }.
//
// `status`-Ableitung (GET):
//   - Tickets unter `pm/.../open/...` -> "open"
//   - Tickets unter `pm/.../archive/...` -> "done"
//   - `milestone.md`-Dateien -> Wert der `Status:`-Zeile (via pm_reader)
//   - sonst leerer String
//
// GET-Response-Form (M012/0004):
//   { ok:true, path, html, status, raw }
//   `raw` ist das ungerenderte Markdown — so muss der Edit-Flow den
//   Inhalt nicht separat holen, um den CodeMirror-Buffer zu fuellen.
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
$action_is_new_milestone = $action === "new_milestone";

if ($method_is_post && $action_is_new_milestone)
{
    // @spec
    // POST ?action=new_milestone legt einen neuen Milestone-Folder an.
    // Vertrag:
    //   - Body JSON: {"slug": "...", "title": "..."}.
    //   - slug: 1-60 Zeichen, ausschliesslich [a-z0-9-], kein fuehrender
    //     oder abschliessender Bindestrich. Sonst 400.
    //   - title: getrimmt 1-120 Zeichen. Sonst 400.
    //   - Naechste freie NNN = max(NNN unter pm/milestones/ UND
    //     pm/milestones/archive/) + 1, dreistellig.
    //   - Legt pm/milestones/NNN-<slug>/{milestone.md, open/.gitkeep,
    //     archive/.gitkeep} an. Bei Kollision 409.
    //   - Antwort 200 + {ok:true, slug:"NNN-<slug>", path:"pm/milestones/NNN-<slug>"}.
    //   - Jede Abweisung wird via app::error_log() protokolliert.
    // @end-spec

    $nm_body_raw = file_get_contents("php://input");

    $nm_body_read_failed = $nm_body_raw === false;

    if ($nm_body_read_failed)
    {
        app::error_log("pm.php new_milestone body read failed.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $nm_payload = json_decode($nm_body_raw, true);

    $nm_payload_is_object =
        is_array($nm_payload)
        && json_last_error() === JSON_ERROR_NONE;

    if (! $nm_payload_is_object)
    {
        app::error_log("pm.php new_milestone rejected: body ist kein JSON.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body ist kein JSON.",
        ]));
    }

    $nm_slug_raw  = isset($nm_payload["slug"]) ? $nm_payload["slug"] : null;
    $nm_title_raw = isset($nm_payload["title"]) ? $nm_payload["title"] : null;

    # Slug validieren: muss String sein, 1-60 Zeichen, nur [a-z0-9-],
    # kein fuehrender/abschliessender Bindestrich.
    $nm_slug_is_string = is_string($nm_slug_raw);
    $nm_slug           = $nm_slug_is_string ? $nm_slug_raw : "";

    $nm_slug_length_ok =
        $nm_slug_is_string
        && strlen($nm_slug) >= 1
        && strlen($nm_slug) <= 60;

    $nm_slug_charset_ok =
        $nm_slug_length_ok
        && preg_match('/^[a-z0-9-]+$/', $nm_slug) === 1;

    $nm_slug_no_edge_dash =
        $nm_slug_charset_ok
        && $nm_slug[0] !== "-"
        && $nm_slug[strlen($nm_slug) - 1] !== "-";

    $nm_slug_is_valid = $nm_slug_no_edge_dash;

    if (! $nm_slug_is_valid)
    {
        app::error_log("pm.php new_milestone rejected slug: " . (string) $nm_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger slug.",
        ]));
    }

    # Titel validieren: muss String sein, getrimmt 1-120 Zeichen.
    $nm_title_is_string = is_string($nm_title_raw);
    $nm_title_trimmed   = $nm_title_is_string ? trim($nm_title_raw) : "";

    $nm_title_is_valid =
        $nm_title_is_string
        && strlen($nm_title_trimmed) >= 1
        && strlen($nm_title_trimmed) <= 120;

    if (! $nm_title_is_valid)
    {
        app::error_log("pm.php new_milestone rejected title for slug: " . $nm_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Titel.",
        ]));
    }

    # Naechste freie NNN bestimmen: scan beide Verzeichnisse.
    $nm_milestones_dir = $repo_root_abs . "/pm/milestones";
    $nm_archive_dir    = $repo_root_abs . "/pm/milestones/archive";

    $nm_active_entries  = @scandir($nm_milestones_dir);
    $nm_archive_entries = @scandir($nm_archive_dir);

    if ($nm_active_entries === false)
    {
        app::error_log("pm.php new_milestone scandir failed for milestones dir.");
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Milestones-Verzeichnis konnte nicht gelesen werden.",
        ]));
    }

    if ($nm_archive_entries === false)
    {
        # Archive-Folder muss existieren (das Repo hat ihn); aber wenn er
        # mal nicht da ist, behandeln wir die Liste als leer und arbeiten
        # weiter — der Folder wird hier nicht angelegt.
        $nm_archive_entries = [];
    }

    $nm_all_entries = array_merge($nm_active_entries, $nm_archive_entries);

    $nm_existing_numbers = [];

    foreach ($nm_all_entries as $nm_entry)
    {
        $nm_entry_is_dotted = $nm_entry === "." || $nm_entry === "..";

        if ($nm_entry_is_dotted)
        {
            continue;
        }

        $nm_entry_long_enough = strlen($nm_entry) >= 3;

        if (! $nm_entry_long_enough)
        {
            continue;
        }

        $nm_prefix = substr($nm_entry, 0, 3);

        $nm_prefix_is_three_digits = ctype_digit($nm_prefix);

        if (! $nm_prefix_is_three_digits)
        {
            continue;
        }

        $nm_existing_numbers[] = (int) $nm_prefix;
    }

    $nm_has_existing = count($nm_existing_numbers) > 0;

    $nm_next_number = $nm_has_existing ? (max($nm_existing_numbers) + 1) : 1;
    $nm_folder_nnn  = sprintf("%03d", $nm_next_number);

    $nm_folder_slug = $nm_folder_nnn . "-" . $nm_slug;
    $nm_folder_rel  = "pm/milestones/" . $nm_folder_slug;
    $nm_folder_abs  = $nm_milestones_dir . "/" . $nm_folder_slug;

    $nm_folder_collision = file_exists($nm_folder_abs);

    if ($nm_folder_collision)
    {
        app::error_log("pm.php new_milestone collision: " . $nm_folder_slug);
        http_response_code(409);
        exit(json_encode([
            "ok"      => false,
            "message" => "Milestone existiert bereits.",
        ]));
    }

    # Folder + Unterordner + Files anlegen.
    $nm_mkdir_root = @mkdir($nm_folder_abs, 0755, true);

    if (! $nm_mkdir_root)
    {
        app::error_log("pm.php new_milestone mkdir failed: " . $nm_folder_abs);
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Folder konnte nicht angelegt werden.",
        ]));
    }

    $nm_mkdir_open    = @mkdir($nm_folder_abs . "/open", 0755);
    $nm_mkdir_archive = @mkdir($nm_folder_abs . "/archive", 0755);

    $nm_subdirs_ok = $nm_mkdir_open && $nm_mkdir_archive;

    if (! $nm_subdirs_ok)
    {
        app::error_log("pm.php new_milestone mkdir subdirs failed: " . $nm_folder_abs);
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Unterordner konnten nicht angelegt werden.",
        ]));
    }

    $nm_gitkeep_open    = @file_put_contents($nm_folder_abs . "/open/.gitkeep", "");
    $nm_gitkeep_archive = @file_put_contents($nm_folder_abs . "/archive/.gitkeep", "");

    $nm_gitkeeps_ok =
        $nm_gitkeep_open !== false
        && $nm_gitkeep_archive !== false;

    if (! $nm_gitkeeps_ok)
    {
        app::error_log("pm.php new_milestone gitkeep write failed: " . $nm_folder_abs);
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Gitkeep-Files konnten nicht geschrieben werden.",
        ]));
    }

    # milestone.md-Template via HEREDOC.
    $nm_milestone_md = <<<MILESTONE_MD
# {$nm_folder_nnn} — {$nm_title_trimmed}

Goal: {$nm_title_trimmed}.

Status: planned

## Tickets

## Out of scope

MILESTONE_MD;

    $nm_md_write_bytes = @file_put_contents($nm_folder_abs . "/milestone.md", $nm_milestone_md);

    $nm_md_write_ok = $nm_md_write_bytes !== false;

    if (! $nm_md_write_ok)
    {
        app::error_log("pm.php new_milestone milestone.md write failed: " . $nm_folder_abs);
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "milestone.md konnte nicht geschrieben werden.",
        ]));
    }

    app::error_log("pm.php new_milestone: " . $nm_folder_slug);

    exit(json_encode([
        "ok"   => true,
        "slug" => $nm_folder_slug,
        "path" => $nm_folder_rel,
    ]));
}

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
    "raw"    => $raw_file_contents,
]));
