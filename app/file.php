<?php

declare(strict_types=1);

# JSON-Endpoint fuer den AJAX-Content-Loader (Ticket 002/0004).
# Liefert eine Datei aus dem SPECKIG_ROOT als gerendertes HTML im JSON-Wrapper.
#   .md     -> Parsedown
#   sonst   -> <pre> + app::escape
# Pfad-Traversal-Schutz ist identisch zu index.php — bewusste Duplikation,
# wird in Ticket 002/0006 konsolidiert. Variablen-Namen sind hier absichtlich
# 1:1 zu index.php gehalten, damit der Move einfach bleibt.
#
# Hinweis: Die Editierbar-Schwarzliste (vendor/.git/spec_parser) lebt doppelt —
# einmal im POST-Save-Handler, einmal im GET-Pfad fuer das `editable`-Flag.
# Eine Konsolidierung in einen Helper ist Folge-Ticket; bewusst inline gelassen,
# um Premature-Abstraktion zu vermeiden (siehe code_style.md).
#
# @spec
# GET  ?path=...                     -> Datei laden, rendern (Markdown via
#                                       Parsedown, sonst <pre>+escape), plus
#                                       optionalen spec_parser-Payload.
#                                       Response enthaelt zusaetzlich `raw`
#                                       (roher Datei-Inhalt) und `editable`
#                                       (bool). `editable=false` fuer Pfade
#                                       in der Schwarzliste (vendor/.git/
#                                       spec_parser), sonst `true`.
# POST ?action=save&path=...         -> Body raw in die Datei schreiben.
#                                       Scope ist SPECKIG_ROOT (alles unter
#                                       dem Repo-Root). Schwarzliste:
#                                       `app/_share/vendor/`, `.git/`,
#                                       `app/_share/spec_parser/`. Binary-Guard
#                                       (NUL-Byte in den ersten 8 KB), Body
#                                       max 1 MB. Atomar via tmp+rename. Siehe
#                                       M013/0001.
# POST ?action=new_file              -> JSON-Body {dir, name}. Legt eine leere
#                                       Datei `dir/name` an. `dir === ""` ist
#                                       Repo-Root. Schwarzliste analog Save.
#                                       Name-Pattern `[A-Za-z0-9._-]{1,120}`,
#                                       kein fuehrender Punkt. 409 bei
#                                       Kollision. Siehe M013/0004.
# Erfolg : HTTP 200 + JSON (Form je nach Aktion).
# Fehler : HTTP 400/409/413/500 + { ok: false, message }.
# @end-spec

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

# --- Method-Dispatch: POST ?action=save -------------------------------------
# Save kommt VOR dem GET-Pfad, damit der bestehende Read-Pfad unangetastet
# bleibt. Siehe M013/0001.

$method_is_post     = $_SERVER["REQUEST_METHOD"] === "POST";
$action             = isset($_GET["action"]) ? (string) $_GET["action"] : "";
$action_is_save     = $action === "save";
$action_is_new_file = $action === "new_file";

if ($method_is_post && $action_is_save)
{
    // @spec
    // POST ?action=save&path=... schreibt den raw Request-Body in die Ziel-
    // datei. Vertrag:
    //   - Scope = SPECKIG_ROOT: jeder Pfad darunter ist erlaubt, AUSSER:
    //       * Schwarzliste-Prefix oder -Substring: `app/_share/vendor/`,
    //         `.git/`, `app/_share/spec_parser/`. Wir pruefen sowohl Prefix
    //         als auch Substring — letzteres als defensive Schicht, falls
    //         ein Symlink o.ae. uns ueber Umwege in einen geschuetzten
    //         Unterbaum bringen koennte.
    //       * Binaer-Inhalt (NUL-Byte in den ersten 8 KB der Body).
    //       * Body > 1 MB.
    //   - Pfad-Regeln: kein `..`, kein fuehrender `/`, parent-realpath muss
    //     existieren und innerhalb SPECKIG_ROOT liegen. Zieldatei selbst
    //     darf neu sein.
    //   - KEINE Endungs-Whitelist (alle Extensions, solange nicht binaer).
    //   - Binary-Guard: erste 8 KB der Body untersuchen — wenn ein `\0`-Byte
    //     drin steckt, lehnen wir ab. Wir verhindern damit, dass Binaer-
    //     dateien (Bilder, .so, etc.) ueber den Editor zerstoert werden,
    //     ohne dass wir UTF-8-Validierung implementieren.
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
        && $save_raw_path[0] !== "/";

    if (! $save_path_string_is_safe)
    {
        app::error_log("file.php save rejected path (string): " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Pfad.",
        ]));
    }

    # Schwarzliste: weder Prefix noch Substring. Substring-Check ist die
    # defensive Schicht (Symlinks, eingeschachtelte Tree-Layouts).
    $save_blacklist = [
        "app/_share/vendor/",
        ".git/",
        "app/_share/spec_parser/",
    ];

    $save_path_is_blacklisted = false;

    foreach ($save_blacklist as $bl_entry)
    {
        $hits_prefix    = str_starts_with($save_raw_path, $bl_entry);
        $hits_substring = str_contains($save_raw_path, $bl_entry);

        if ($hits_prefix || $hits_substring)
        {
            $save_path_is_blacklisted = true;
            break;
        }
    }

    if ($save_path_is_blacklisted)
    {
        app::error_log("file.php save rejected blacklisted path: " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Pfad nicht editierbar.",
        ]));
    }

    # parent-Check: das Parent-Verzeichnis muss existieren und realpath
    # innerhalb SPECKIG_ROOT liegen. Sonderfall: parent_rel === "." (Datei
    # direkt im Repo-Root) — dann ist parent_abs === speckig_root_abs, das
    # erlauben wir direkt.
    $parent_rel = dirname($save_raw_path);

    $parent_is_root = $parent_rel === ".";

    if ($parent_is_root)
    {
        $parent_abs = $speckig_root_abs;
    }
    else
    {
        $parent_abs = realpath($speckig_root_abs . "/" . $parent_rel);
    }

    $parent_resolves =
        $speckig_root_abs !== false
        && $parent_abs !== false;

    $parent_is_inside_root =
        $parent_resolves
        && (
            $parent_is_root
            || str_starts_with($parent_abs, $speckig_root_abs . DIRECTORY_SEPARATOR)
        );

    $parent_is_dir = $parent_resolves && is_dir($parent_abs);

    $save_parent_is_valid =
        $parent_is_inside_root
        && $parent_is_dir;

    if (! $save_parent_is_valid)
    {
        app::error_log("file.php save rejected path (parent): " . $save_raw_path);
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
        app::error_log("file.php save body read failed for: " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $body_is_too_large = strlen($body) > 1048576;

    if ($body_is_too_large)
    {
        app::error_log("file.php save body too large for: " . $save_raw_path);
        http_response_code(413);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body zu gross.",
        ]));
    }

    # Binary-Guard: erste 8 KB der Body auf NUL-Byte pruefen. Damit fangen
    # wir versehentliche Binaer-Uploads ab, ohne UTF-8-Validierung.
    $body_head           = substr($body, 0, 8192);
    $body_looks_binary   = str_contains($body_head, "\0");

    if ($body_looks_binary)
    {
        app::error_log("file.php save rejected binary content for: " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Binaere Inhalte nicht erlaubt.",
        ]));
    }

    # Atomar schreiben: tmp + rename.
    $target_abs = $speckig_root_abs . "/" . $save_raw_path;
    $tmp_abs    = $target_abs . ".tmp." . bin2hex(random_bytes(4));

    $bytes_written = @file_put_contents($tmp_abs, $body);

    $tmp_write_failed = $bytes_written === false;

    if ($tmp_write_failed)
    {
        app::error_log("file.php save tmp write failed for: " . $save_raw_path);

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
        app::error_log("file.php save rename failed for: " . $save_raw_path);

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

# --- Method-Dispatch: POST ?action=new_file ---------------------------------
# Legt eine leere Datei im angegebenen Repo-Ordner an. Siehe M013/0004.
#
# Hinweis: Die Editierbar-Schwarzliste lebt JETZT dreifach inline — Save,
# GET-editable-Flag, new_file. Konsolidierung in einen Helper ist Folge-
# Ticket (bewusst inline gehalten, um Premature-Abstraktion zu vermeiden,
# siehe code_style.md).

if ($method_is_post && $action_is_new_file)
{
    // @spec
    // POST ?action=new_file mit JSON-Body {dir, name} legt eine leere Datei
    // `<dir>/<name>` an. Vertrag:
    //   - `dir`: String, kein `..`, kein fuehrender `/`. `dir === ""` meint
    //     Repo-Root.
    //   - `dir` muss `is_dir` innerhalb SPECKIG_ROOT sein.
    //   - Schwarzliste (Prefix + Substring + exakter Match): `app/_share/vendor`,
    //     `.git`, `app/_share/spec_parser`. Treffer -> 400.
    //   - `name`: Pattern `[A-Za-z0-9._-]{1,120}`, kein fuehrender Punkt
    //     (Hidden-Files verbieten), kein Slash, kein `..`.
    //   - Kollision (`file_exists`) -> 409 `Datei existiert bereits.`.
    //   - Anlegen via `file_put_contents($target, "")`. Bei false -> 500.
    //   - Antwort 200 + {ok:true, path:"<dir>/<name>"} bzw. {ok:true,
    //     path:"<name>"} falls dir leer.
    //   - Jede Abweisung loggt via `app::error_log()`.
    // @end-spec

    $nf_body = file_get_contents("php://input");

    $nf_body_read_failed = $nf_body === false;

    if ($nf_body_read_failed)
    {
        app::error_log("file.php new_file body read failed");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $nf_payload = json_decode($nf_body, true);

    $nf_payload_is_object =
        is_array($nf_payload)
        && ! array_is_list($nf_payload);

    if (! $nf_payload_is_object)
    {
        app::error_log("file.php new_file body not JSON object");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body ist kein JSON.",
        ]));
    }

    $nf_dir_raw  = isset($nf_payload["dir"])  ? $nf_payload["dir"]  : null;
    $nf_name_raw = isset($nf_payload["name"]) ? $nf_payload["name"] : null;

    $nf_dir_is_string  = is_string($nf_dir_raw);
    $nf_name_is_string = is_string($nf_name_raw);

    if (! ($nf_dir_is_string && $nf_name_is_string))
    {
        app::error_log("file.php new_file payload missing/typed wrong");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body ist kein JSON.",
        ]));
    }

    $nf_dir  = (string) $nf_dir_raw;
    $nf_name = (string) $nf_name_raw;

    # --- dir-Validierung ----------------------------------------------------
    # Leer ist erlaubt (= Repo-Root). Sonst keine Traversal-Bestandteile.
    $nf_dir_is_empty = $nf_dir === "";

    $nf_dir_string_is_safe =
        $nf_dir_is_empty
        || (
            ! str_contains($nf_dir, "..")
            && $nf_dir[0] !== "/"
        );

    if (! $nf_dir_string_is_safe)
    {
        app::error_log("file.php new_file rejected dir (string): " . $nf_dir);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Ordner.",
        ]));
    }

    # Schwarzliste analog Save-Handler — Prefix UND Substring, plus
    # exakte Treffer ohne Trailing-Slash (sonst koennte `dir = "app/_share/vendor"`
    # durchschluepfen, weil die Liste auf `vendor/` endet).
    $nf_blacklist_with_slash = [
        "app/_share/vendor/",
        ".git/",
        "app/_share/spec_parser/",
    ];

    $nf_blacklist_exact = [
        "app/_share/vendor",
        ".git",
        "app/_share/spec_parser",
    ];

    $nf_dir_is_blacklisted = false;

    foreach ($nf_blacklist_with_slash as $bl_entry)
    {
        $hits_prefix    = str_starts_with($nf_dir, $bl_entry);
        $hits_substring = str_contains($nf_dir, $bl_entry);

        if ($hits_prefix || $hits_substring)
        {
            $nf_dir_is_blacklisted = true;
            break;
        }
    }

    if (! $nf_dir_is_blacklisted)
    {
        foreach ($nf_blacklist_exact as $bl_entry)
        {
            if ($nf_dir === $bl_entry)
            {
                $nf_dir_is_blacklisted = true;
                break;
            }
        }
    }

    if ($nf_dir_is_blacklisted)
    {
        app::error_log("file.php new_file rejected blacklisted dir: " . $nf_dir);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ordner nicht editierbar.",
        ]));
    }

    # dir_abs aufloesen — Sonderfall leere dir == Repo-Root.
    if ($nf_dir_is_empty)
    {
        $nf_dir_abs = $speckig_root_abs;
    }
    else
    {
        $nf_dir_abs = realpath($speckig_root_abs . "/" . $nf_dir);
    }

    $nf_dir_resolved =
        $speckig_root_abs !== false
        && $nf_dir_abs !== false;

    $nf_dir_is_inside_root =
        $nf_dir_resolved
        && (
            $nf_dir_is_empty
            || str_starts_with($nf_dir_abs, $speckig_root_abs . DIRECTORY_SEPARATOR)
        );

    $nf_dir_is_directory =
        $nf_dir_resolved
        && is_dir($nf_dir_abs);

    $nf_dir_is_valid =
        $nf_dir_is_inside_root
        && $nf_dir_is_directory;

    if (! $nf_dir_is_valid)
    {
        app::error_log("file.php new_file rejected dir (fs): " . $nf_dir);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ordner nicht gefunden.",
        ]));
    }

    # --- name-Validierung ---------------------------------------------------
    # Pattern: [A-Za-z0-9._-], 1-120 Zeichen, kein fuehrender Punkt
    # (Hidden-Files), kein Slash, kein `..`. Wir defensiv pruefen mehrfach,
    # auch wenn das Regex schon viel davon abdeckt.
    $nf_name_length      = strlen($nf_name);
    $nf_name_length_ok   = $nf_name_length >= 1 && $nf_name_length <= 120;
    $nf_name_charset_ok  = (bool) preg_match('/^[A-Za-z0-9._-]+$/', $nf_name);
    $nf_name_starts_dot  = $nf_name_length > 0 && $nf_name[0] === ".";
    $nf_name_has_slash   = str_contains($nf_name, "/");
    $nf_name_has_dotdot  = str_contains($nf_name, "..");

    $nf_name_is_valid =
        $nf_name_length_ok
        && $nf_name_charset_ok
        && ! $nf_name_starts_dot
        && ! $nf_name_has_slash
        && ! $nf_name_has_dotdot;

    if (! $nf_name_is_valid)
    {
        app::error_log("file.php new_file rejected name: " . $nf_name);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Dateiname.",
        ]));
    }

    # --- Kollisionspruefung -------------------------------------------------
    $nf_target_abs = $nf_dir_abs . "/" . $nf_name;

    $nf_target_exists = file_exists($nf_target_abs);

    if ($nf_target_exists)
    {
        app::error_log("file.php new_file collision: " . $nf_dir . "/" . $nf_name);
        http_response_code(409);
        exit(json_encode([
            "ok"      => false,
            "message" => "Datei existiert bereits.",
        ]));
    }

    # --- Anlegen ------------------------------------------------------------
    $nf_write_result = @file_put_contents($nf_target_abs, "");

    $nf_write_failed = $nf_write_result === false;

    if ($nf_write_failed)
    {
        app::error_log("file.php new_file write failed: " . $nf_dir . "/" . $nf_name);
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Anlegen fehlgeschlagen.",
        ]));
    }

    $nf_response_path =
        $nf_dir_is_empty
            ? $nf_name
            : ($nf_dir . "/" . $nf_name);

    app::error_log("file.php new_file created: " . $nf_response_path);

    exit(json_encode([
        "ok"   => true,
        "path" => $nf_response_path,
    ]));
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
    || $file_extension === "js"
    || $file_extension === "nim"
    || $file_extension === "lua"
    || $file_extension === "ts"
    || $file_extension === "groovy";

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

# --- editable-Flag (M013/0003) ----------------------------------------------
# Schwarzliste analog zum Save-Handler oben — bewusste Duplikation, weil die
# Liste in beiden Codepfaden inline klarer steht als hinter einem Helper.
# Eine Konsolidierung in einen einzigen Helper ist Folge-Ticket.

$path_is_vendor =
    str_starts_with($raw_path, "app/_share/vendor/")
    || str_contains($raw_path, "/app/_share/vendor/");

$path_is_git =
    str_starts_with($raw_path, ".git/")
    || str_contains($raw_path, "/.git/");

$path_is_spec_parser =
    str_starts_with($raw_path, "app/_share/spec_parser/")
    || str_contains($raw_path, "/app/_share/spec_parser/");

$editable_flag = ! ($path_is_vendor || $path_is_git || $path_is_spec_parser);

# --- success response -------------------------------------------------------

exit(json_encode([
    "ok"       => true,
    "path"     => $raw_path,
    "html"     => $rendered_html,
    "spec"     => $spec_payload,
    "raw"      => $raw_file_contents,
    "editable" => $editable_flag,
]));
