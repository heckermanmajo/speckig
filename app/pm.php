<?php

declare(strict_types=1);

// @spec
// JSON-Endpoint fuer den Plan-View AJAX-Loader (M006/0003) plus
// Save-Endpoint fuer den CodeMirror-Editor (M012/0002, M014/0001) plus
// new_milestone-Endpoint fuer die "+ Milestone"-Action (M012/0005) plus
// new_ticket-Endpoint fuer die "+ Ticket"-Action (M012/0006) plus
// new_idea-Endpoint fuer die "+ Idee"-Action in der Info-Sidebar
// (M014/0003) plus new_report-Endpoint fuer die "+ Report"-Action in
// der Info-Sidebar mit globaler Numerierung (M014/0004).
//
// GET  ?path=pm/...                  -> Markdown laden + rendern.
// POST ?action=save&path=pm/....md   -> Body raw in die Datei schreiben.
//                                       Akzeptiert zwei Pfad-Schichten:
//                                         (a) Info-Whitelist via
//                                             `app::pm_write_kind()` —
//                                             pm/ideas/, pm/reports/,
//                                             pm/audits/, pm/terms/,
//                                             pm/decisions/.
//                                         (b) Legacy-Whitelist via
//                                             `app::pm_path_kind_legacy()` —
//                                             milestone.md, aktive
//                                             Milestone-Tickets unter
//                                             open/, aktive Bug-Tickets
//                                             unter pm/bugs/open/.
//                                       Decisions sind append-only
//                                       (zweite POST gegen existierende
//                                       Datei -> 409, siehe
//                                       pm/how-to/decisions.md).
// POST ?action=new_milestone         -> Neuen Milestone-Folder anlegen.
//                                       Body JSON: {slug, title}.
//                                       Antwort: {ok:true, slug, path}.
// POST ?action=new_ticket            -> Neues Ticket in einem aktiven
//                                       Milestone anlegen + Bullet in
//                                       milestone.md vor "## Out of scope".
//                                       Body JSON: {milestone_slug, slug, title}.
//                                       Antwort: {ok:true, slug, path}.
// POST ?action=new_idea              -> Neue Idea-Datei pm/ideas/<slug>.md
//                                       aus Template anlegen (M014/0003).
//                                       Body JSON: {slug}. Slug-Whitelist
//                                       `^[a-z0-9][a-z0-9-]*$`, Laenge <=80.
//                                       Kollision -> 409 (kein stilles
//                                       Ueberschreiben). Atomar via
//                                       tmp+rename.
//                                       Antwort: {ok:true, path:"pm/ideas/<slug>.md"}.
// POST ?action=new_report            -> Neuen Report unter
//                                       pm/reports/NNNN-<slug>.md anlegen
//                                       (M014/0004). Body JSON:
//                                       {slug, type}. Slug-Whitelist
//                                       `^[a-z0-9][a-z0-9-]*$`, Laenge <=80.
//                                       Type-Whitelist: research/audit/
//                                       comparison. Globale Numerierung:
//                                       max(existierender NNNN in
//                                       pm/reports/) + 1, vierstellig
//                                       zero-padded. Atomar via tmp+rename.
//                                       Antwort: {ok:true, path, number}.
//
// Erfolg : HTTP 200 + JSON (Form je nach Aktion, siehe unten).
// Fehler : HTTP 400/409/413/500 + { ok: false, message }.
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
$action_is_new_ticket    = $action === "new_ticket";
$action_is_new_idea      = $action === "new_idea";
$action_is_new_report    = $action === "new_report";

if ($method_is_post && $action_is_new_idea)
{
    // @spec
    // POST ?action=new_idea legt eine neue Idea-Datei unter pm/ideas/<slug>.md
    // aus einem Template an.
    // Vertrag:
    //   - Body JSON: {"slug": "..."}.
    //   - slug: 1-80 Zeichen, ausschliesslich [a-z0-9-], MUSS mit einem
    //     [a-z0-9] starten (kein fuehrender Bindestrich). Form-Regex
    //     `^[a-z0-9][a-z0-9-]*$`. Sonst 400.
    //   - Kein `..`, kein `/`, kein `.` — fallen automatisch durch das
    //     Charset-Regex, aber Traversal-Wert wie "../etc" wird damit hart
    //     verworfen.
    //   - Zielpfad: pm/ideas/<slug>.md. Wenn die Datei bereits existiert,
    //     409 `Idea existiert schon.` (kein stilles Ueberschreiben).
    //   - Template fest verdrahtet (siehe pm/how-to/ideas.md: Titel-Zeile
    //     + One-line essence + Notes/Sketches). Begruendung: das How-to
    //     hat keinen klar abgegrenzten Template-Block zum Parsen — die
    //     Form ist bewusst lose. Stattdessen wird die im How-to
    //     dokumentierte Minimalform direkt im Endpoint reproduziert,
    //     damit Anlegen ohne Parser robust bleibt.
    //   - Schreiben atomar via tmp + rename.
    //   - Antwort 200 + {ok:true, path:"pm/ideas/<slug>.md"}.
    //   - Jede Abweisung wird via app::error_log() protokolliert.
    // @end-spec

    $ni_body_raw = file_get_contents("php://input");

    $ni_body_read_failed = $ni_body_raw === false;

    if ($ni_body_read_failed)
    {
        app::error_log("pm.php new_idea body read failed.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $ni_payload = json_decode($ni_body_raw, true);

    $ni_payload_is_object =
        is_array($ni_payload)
        && json_last_error() === JSON_ERROR_NONE;

    if (! $ni_payload_is_object)
    {
        app::error_log("pm.php new_idea rejected: body ist kein JSON.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body ist kein JSON.",
        ]));
    }

    $ni_slug_raw = isset($ni_payload["slug"]) ? $ni_payload["slug"] : null;

    # Slug validieren: String, 1-80 Zeichen, Regex ^[a-z0-9][a-z0-9-]*$.
    $ni_slug_is_string = is_string($ni_slug_raw);
    $ni_slug           = $ni_slug_is_string ? $ni_slug_raw : "";

    $ni_slug_length_ok =
        $ni_slug_is_string
        && strlen($ni_slug) >= 1
        && strlen($ni_slug) <= 80;

    $ni_slug_shape_ok =
        $ni_slug_length_ok
        && preg_match('/^[a-z0-9][a-z0-9-]*$/', $ni_slug) === 1;

    if (! $ni_slug_shape_ok)
    {
        app::error_log("pm.php new_idea rejected slug: " . (string) $ni_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger slug.",
        ]));
    }

    $ni_file_rel = "pm/ideas/" . $ni_slug . ".md";
    $ni_file_abs = $repo_root_abs . "/" . $ni_file_rel;

    # Kollision: schon vorhandene Idea wird nicht ueberschrieben.
    $ni_file_collision = file_exists($ni_file_abs);

    if ($ni_file_collision)
    {
        app::error_log("pm.php new_idea collision: " . $ni_file_rel);
        http_response_code(409);
        exit(json_encode([
            "ok"      => false,
            "message" => "Idea existiert schon.",
        ]));
    }

    # Template fest verdrahtet (siehe Spec oben + pm/how-to/ideas.md).
    # Minimalform: Titel-Zeile aus dem Slug, leere Platzhalter-Sektion fuer
    # One-line essence und Notes. Der User editiert die Datei direkt im
    # Anschluss im Edit-Mode (M014/0002).
    $ni_template = <<<IDEA_MD
# {$ni_slug}

One-line essence.

Notes, sketches, open questions.

IDEA_MD;

    # Atomar schreiben: tmp + rename.
    $ni_tmp_abs = $ni_file_abs . ".tmp." . bin2hex(random_bytes(4));

    $ni_tmp_write_bytes = @file_put_contents($ni_tmp_abs, $ni_template);

    $ni_tmp_write_failed = $ni_tmp_write_bytes === false;

    if ($ni_tmp_write_failed)
    {
        app::error_log("pm.php new_idea tmp write failed: " . $ni_file_rel);

        if (is_file($ni_tmp_abs))
        {
            @unlink($ni_tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Schreiben fehlgeschlagen.",
        ]));
    }

    $ni_rename_ok = @rename($ni_tmp_abs, $ni_file_abs);

    if (! $ni_rename_ok)
    {
        app::error_log("pm.php new_idea rename failed: " . $ni_file_rel);

        if (is_file($ni_tmp_abs))
        {
            @unlink($ni_tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Rename fehlgeschlagen.",
        ]));
    }

    app::error_log("pm.php new_idea: " . $ni_file_rel);

    exit(json_encode([
        "ok"   => true,
        "path" => $ni_file_rel,
    ]));
}

if ($method_is_post && $action_is_new_report)
{
    // @spec
    // POST ?action=new_report legt einen neuen Report unter
    // pm/reports/NNNN-<slug>.md aus einem Template an.
    // Vertrag:
    //   - Body JSON: {"slug": "...", "type": "research|audit|comparison"}.
    //   - slug: 1-80 Zeichen, ausschliesslich [a-z0-9-], MUSS mit einem
    //     [a-z0-9] starten (kein fuehrender Bindestrich). Form-Regex
    //     `^[a-z0-9][a-z0-9-]*$`. Sonst 400.
    //   - type: einer aus {"research", "audit", "comparison"}. Sonst 400.
    //   - Naechste Nummer NNNN: scandir(pm/reports/), alle Eintraege
    //     `NNNN-*.md` matchen, max+1. Bei leerem Verzeichnis -> 1. Wird
    //     vierstellig zero-padded. Numerierung ist GLOBAL und wird nie
    //     wiederverwendet (siehe pm/how-to/reports.md).
    //   - Zielpfad: pm/reports/NNNN-<slug>.md. Bei Kollision (sollte
    //     durch max+1 nicht passieren, defensive Schicht) -> 409.
    //   - Template fest verdrahtet (siehe pm/how-to/reports.md). Title
    //     wird aus dem Slug abgeleitet (Bindestriche -> Spaces,
    //     ucwords). Datum via date("Y-m-d"). Status fest "draft".
    //   - Schreiben atomar via tmp + rename.
    //   - Antwort 200 + {ok:true, path:"pm/reports/NNNN-<slug>.md", number:"NNNN"}.
    //   - Jede Abweisung wird via app::error_log() protokolliert.
    // @end-spec

    $nr_body_raw = file_get_contents("php://input");

    $nr_body_read_failed = $nr_body_raw === false;

    if ($nr_body_read_failed)
    {
        app::error_log("pm.php new_report body read failed.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $nr_payload = json_decode($nr_body_raw, true);

    $nr_payload_is_object =
        is_array($nr_payload)
        && json_last_error() === JSON_ERROR_NONE;

    if (! $nr_payload_is_object)
    {
        app::error_log("pm.php new_report rejected: body ist kein JSON.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body ist kein JSON.",
        ]));
    }

    $nr_slug_raw = isset($nr_payload["slug"]) ? $nr_payload["slug"] : null;
    $nr_type_raw = isset($nr_payload["type"]) ? $nr_payload["type"] : null;

    # Slug validieren: String, 1-80 Zeichen, Regex ^[a-z0-9][a-z0-9-]*$.
    $nr_slug_is_string = is_string($nr_slug_raw);
    $nr_slug           = $nr_slug_is_string ? $nr_slug_raw : "";

    $nr_slug_length_ok =
        $nr_slug_is_string
        && strlen($nr_slug) >= 1
        && strlen($nr_slug) <= 80;

    $nr_slug_shape_ok =
        $nr_slug_length_ok
        && preg_match('/^[a-z0-9][a-z0-9-]*$/', $nr_slug) === 1;

    if (! $nr_slug_shape_ok)
    {
        app::error_log("pm.php new_report rejected slug: " . (string) $nr_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger slug.",
        ]));
    }

    # Type-Whitelist hart pruefen.
    $nr_type_is_string = is_string($nr_type_raw);
    $nr_type           = $nr_type_is_string ? $nr_type_raw : "";

    $nr_allowed_types = ["research", "audit", "comparison"];

    $nr_type_is_allowed =
        $nr_type_is_string
        && in_array($nr_type, $nr_allowed_types, true);

    if (! $nr_type_is_allowed)
    {
        app::error_log("pm.php new_report rejected type: " . (string) $nr_type);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger type.",
        ]));
    }

    # Naechste globale Nummer bestimmen: scandir(pm/reports/), alle
    # `NNNN-*.md`-Eintraege matchen, max+1. Bei leer -> 1.
    $nr_reports_dir = $repo_root_abs . "/pm/reports";

    $nr_entries = @scandir($nr_reports_dir);

    if ($nr_entries === false)
    {
        $nr_entries = [];
    }

    $nr_existing_numbers = [];

    foreach ($nr_entries as $nr_entry)
    {
        $nr_entry_is_dotted = $nr_entry === "." || $nr_entry === "..";

        if ($nr_entry_is_dotted)
        {
            continue;
        }

        $nr_entry_is_md = str_ends_with($nr_entry, ".md");

        if (! $nr_entry_is_md)
        {
            continue;
        }

        $nr_entry_long_enough = strlen($nr_entry) >= 4;

        if (! $nr_entry_long_enough)
        {
            continue;
        }

        $nr_prefix = substr($nr_entry, 0, 4);

        $nr_prefix_is_four_digits = ctype_digit($nr_prefix);

        if (! $nr_prefix_is_four_digits)
        {
            continue;
        }

        $nr_existing_numbers[] = (int) $nr_prefix;
    }

    $nr_has_existing = count($nr_existing_numbers) > 0;

    $nr_next_number = $nr_has_existing ? (max($nr_existing_numbers) + 1) : 1;
    $nr_file_nnnn   = sprintf("%04d", $nr_next_number);

    $nr_file_rel = "pm/reports/" . $nr_file_nnnn . "-" . $nr_slug . ".md";
    $nr_file_abs = $repo_root_abs . "/" . $nr_file_rel;

    # Kollisionspruefung (defensive Schicht — max+1 sollte das nie treffen).
    $nr_file_collision = file_exists($nr_file_abs);

    if ($nr_file_collision)
    {
        app::error_log("pm.php new_report collision: " . $nr_file_rel);
        http_response_code(409);
        exit(json_encode([
            "ok"      => false,
            "message" => "Report existiert schon.",
        ]));
    }

    # Title aus Slug ableiten: "agent-smoke" -> "Agent Smoke".
    $nr_title = ucwords(str_replace("-", " ", $nr_slug));

    # Datum auf heute.
    $nr_today = date("Y-m-d");

    # Template fest verdrahtet (siehe Spec oben + pm/how-to/reports.md).
    $nr_template = <<<REPORT_MD
# {$nr_file_nnnn} — {$nr_title}

Date: {$nr_today}
Type: {$nr_type}
Status: draft

## TL;DR

## Findings

## Sources

## Hooks for us

REPORT_MD;

    # Atomar schreiben: tmp + rename.
    $nr_tmp_abs = $nr_file_abs . ".tmp." . bin2hex(random_bytes(4));

    $nr_tmp_write_bytes = @file_put_contents($nr_tmp_abs, $nr_template);

    $nr_tmp_write_failed = $nr_tmp_write_bytes === false;

    if ($nr_tmp_write_failed)
    {
        app::error_log("pm.php new_report tmp write failed: " . $nr_file_rel);

        if (is_file($nr_tmp_abs))
        {
            @unlink($nr_tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Schreiben fehlgeschlagen.",
        ]));
    }

    $nr_rename_ok = @rename($nr_tmp_abs, $nr_file_abs);

    if (! $nr_rename_ok)
    {
        app::error_log("pm.php new_report rename failed: " . $nr_file_rel);

        if (is_file($nr_tmp_abs))
        {
            @unlink($nr_tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Rename fehlgeschlagen.",
        ]));
    }

    app::error_log("pm.php new_report: " . $nr_file_rel);

    exit(json_encode([
        "ok"     => true,
        "path"   => $nr_file_rel,
        "number" => $nr_file_nnnn,
    ]));
}

if ($method_is_post && $action_is_new_ticket)
{
    // @spec
    // POST ?action=new_ticket legt ein neues Ticket im angegebenen aktiven
    // Milestone an und fuegt eine Bullet-Zeile in dessen milestone.md vor
    // "## Out of scope" ein.
    // Vertrag:
    //   - Body JSON: {"milestone_slug": "NNN-...", "slug": "...", "title": "..."}.
    //   - milestone_slug: Form NNN-... (1-100 Zeichen, nur [a-z0-9-]); muss
    //     als Ordner unter pm/milestones/<milestone_slug>/ existieren. Archive
    //     (pm/milestones/archive/...) wird bewusst NICHT akzeptiert — Archive
    //     ist read-only. Sonst 400.
    //   - slug: 1-60 Zeichen, ausschliesslich [a-z0-9-], kein fuehrender oder
    //     abschliessender Bindestrich. Sonst 400.
    //   - title: getrimmt 1-120 Zeichen. Sonst 400.
    //   - Naechste freie NNNN = max(NNNN in open/ + archive/ des Milestone) + 1,
    //     vierstellig. Bei Kollision 409.
    //   - Legt pm/milestones/<milestone_slug>/open/NNNN-<slug>.md aus Template
    //     an. Trip-tmp+rename fuer milestone.md (bestehende Datei).
    //   - Antwort 200 + {ok:true, slug:"NNNN-<slug>", path:"pm/milestones/<ms>/open/NNNN-<slug>.md"}.
    //   - Jede Abweisung wird via app::error_log() protokolliert.
    // @end-spec

    $nt_body_raw = file_get_contents("php://input");

    $nt_body_read_failed = $nt_body_raw === false;

    if ($nt_body_read_failed)
    {
        app::error_log("pm.php new_ticket body read failed.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body konnte nicht gelesen werden.",
        ]));
    }

    $nt_payload = json_decode($nt_body_raw, true);

    $nt_payload_is_object =
        is_array($nt_payload)
        && json_last_error() === JSON_ERROR_NONE;

    if (! $nt_payload_is_object)
    {
        app::error_log("pm.php new_ticket rejected: body ist kein JSON.");
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Body ist kein JSON.",
        ]));
    }

    $nt_milestone_slug_raw = isset($nt_payload["milestone_slug"]) ? $nt_payload["milestone_slug"] : null;
    $nt_slug_raw           = isset($nt_payload["slug"])           ? $nt_payload["slug"]           : null;
    $nt_title_raw          = isset($nt_payload["title"])          ? $nt_payload["title"]          : null;

    # milestone_slug validieren: String, 1-100 Zeichen, [a-z0-9-], Form NNN-...
    # (drei Ziffern + Bindestrich + Rest).
    $nt_ms_slug_is_string = is_string($nt_milestone_slug_raw);
    $nt_ms_slug           = $nt_ms_slug_is_string ? $nt_milestone_slug_raw : "";

    $nt_ms_slug_length_ok =
        $nt_ms_slug_is_string
        && strlen($nt_ms_slug) >= 1
        && strlen($nt_ms_slug) <= 100;

    $nt_ms_slug_charset_ok =
        $nt_ms_slug_length_ok
        && preg_match('/^[a-z0-9-]+$/', $nt_ms_slug) === 1;

    $nt_ms_slug_is_valid_shape =
        $nt_ms_slug_charset_ok
        && preg_match('/^[0-9]{3}-/', $nt_ms_slug) === 1;

    if (! $nt_ms_slug_is_valid_shape)
    {
        app::error_log("pm.php new_ticket rejected milestone_slug: " . (string) $nt_ms_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger milestone_slug.",
        ]));
    }

    # Milestone-Ordner muss aktiv (nicht archive) existieren.
    $nt_milestone_abs = $repo_root_abs . "/pm/milestones/" . $nt_ms_slug;

    $nt_milestone_exists = is_dir($nt_milestone_abs);

    if (! $nt_milestone_exists)
    {
        app::error_log("pm.php new_ticket milestone does not exist: " . $nt_ms_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Milestone existiert nicht.",
        ]));
    }

    # slug validieren: String, 1-60 Zeichen, [a-z0-9-], kein fuehrender oder
    # abschliessender Bindestrich.
    $nt_slug_is_string = is_string($nt_slug_raw);
    $nt_slug           = $nt_slug_is_string ? $nt_slug_raw : "";

    $nt_slug_length_ok =
        $nt_slug_is_string
        && strlen($nt_slug) >= 1
        && strlen($nt_slug) <= 60;

    $nt_slug_charset_ok =
        $nt_slug_length_ok
        && preg_match('/^[a-z0-9-]+$/', $nt_slug) === 1;

    $nt_slug_no_edge_dash =
        $nt_slug_charset_ok
        && $nt_slug[0] !== "-"
        && $nt_slug[strlen($nt_slug) - 1] !== "-";

    $nt_slug_is_valid = $nt_slug_no_edge_dash;

    if (! $nt_slug_is_valid)
    {
        app::error_log("pm.php new_ticket rejected slug: " . (string) $nt_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger slug.",
        ]));
    }

    # Titel validieren: String, getrimmt 1-120 Zeichen.
    $nt_title_is_string = is_string($nt_title_raw);
    $nt_title_trimmed   = $nt_title_is_string ? trim($nt_title_raw) : "";

    $nt_title_is_valid =
        $nt_title_is_string
        && strlen($nt_title_trimmed) >= 1
        && strlen($nt_title_trimmed) <= 120;

    if (! $nt_title_is_valid)
    {
        app::error_log("pm.php new_ticket rejected title for milestone: " . $nt_ms_slug);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ungueltiger Titel.",
        ]));
    }

    # Naechste freie NNNN bestimmen: scan open/ + archive/ des Milestones.
    $nt_open_dir    = $nt_milestone_abs . "/open";
    $nt_archive_dir = $nt_milestone_abs . "/archive";

    $nt_open_entries    = @scandir($nt_open_dir);
    $nt_archive_entries = @scandir($nt_archive_dir);

    if ($nt_open_entries === false)
    {
        $nt_open_entries = [];
    }

    if ($nt_archive_entries === false)
    {
        $nt_archive_entries = [];
    }

    $nt_all_entries = array_merge($nt_open_entries, $nt_archive_entries);

    $nt_existing_numbers = [];

    foreach ($nt_all_entries as $nt_entry)
    {
        $nt_entry_is_dotted = $nt_entry === "." || $nt_entry === "..";

        if ($nt_entry_is_dotted)
        {
            continue;
        }

        $nt_entry_is_md = str_ends_with($nt_entry, ".md");

        if (! $nt_entry_is_md)
        {
            continue;
        }

        $nt_entry_long_enough = strlen($nt_entry) >= 4;

        if (! $nt_entry_long_enough)
        {
            continue;
        }

        $nt_prefix = substr($nt_entry, 0, 4);

        $nt_prefix_is_four_digits = ctype_digit($nt_prefix);

        if (! $nt_prefix_is_four_digits)
        {
            continue;
        }

        $nt_existing_numbers[] = (int) $nt_prefix;
    }

    $nt_has_existing = count($nt_existing_numbers) > 0;

    $nt_next_number = $nt_has_existing ? (max($nt_existing_numbers) + 1) : 1;
    $nt_file_nnnn   = sprintf("%04d", $nt_next_number);

    $nt_file_slug = $nt_file_nnnn . "-" . $nt_slug;
    $nt_file_rel  = "pm/milestones/" . $nt_ms_slug . "/open/" . $nt_file_slug . ".md";
    $nt_file_abs  = $nt_open_dir . "/" . $nt_file_slug . ".md";
    $nt_archive_collision_abs = $nt_archive_dir . "/" . $nt_file_slug . ".md";

    # Kollisionspruefung: weder open/ noch archive/ darf das File schon haben.
    $nt_file_collision =
        file_exists($nt_file_abs)
        || file_exists($nt_archive_collision_abs);

    if ($nt_file_collision)
    {
        app::error_log("pm.php new_ticket collision: " . $nt_file_rel);
        http_response_code(409);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ticket existiert bereits.",
        ]));
    }

    # Ticket-Template via HEREDOC.
    $nt_ticket_md = <<<TICKET_MD
# {$nt_file_nnnn} — {$nt_title_trimmed}

{$nt_title_trimmed}.

## Done when
-

## Verifikation
-

## Out of scope
-

TICKET_MD;

    $nt_md_write_bytes = @file_put_contents($nt_file_abs, $nt_ticket_md);

    $nt_md_write_ok = $nt_md_write_bytes !== false;

    if (! $nt_md_write_ok)
    {
        app::error_log("pm.php new_ticket write failed: " . $nt_file_abs);
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "Ticket-File konnte nicht geschrieben werden.",
        ]));
    }

    # Bullet in milestone.md einfuegen — vor "## Out of scope" und vor der
    # eventuellen Leerzeile davor. Atomar via tmp+rename.
    $nt_milestone_md_abs = $nt_milestone_abs . "/milestone.md";

    $nt_existing_lines = @file($nt_milestone_md_abs);

    if ($nt_existing_lines === false)
    {
        app::error_log("pm.php new_ticket milestone.md read failed: " . $nt_milestone_md_abs);
        # Ticket-File wurde schon geschrieben — wir lassen es liegen und
        # melden den Fehler; manueller Eingriff ist sicherer als Auto-Rollback,
        # weil das geschriebene Ticket gueltig ist.
        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "milestone.md konnte nicht gelesen werden.",
        ]));
    }

    $nt_bullet_line = "- [ ] open/" . $nt_file_slug . ".md — " . $nt_title_trimmed . "\n";

    # Index von "## Out of scope" finden.
    $nt_out_of_scope_index = -1;

    for ($nt_i = 0; $nt_i < count($nt_existing_lines); $nt_i++)
    {
        $nt_line = $nt_existing_lines[$nt_i];

        $nt_line_is_out_of_scope = str_starts_with(ltrim($nt_line), "## Out of scope");

        if ($nt_line_is_out_of_scope)
        {
            $nt_out_of_scope_index = $nt_i;
            break;
        }
    }

    $nt_has_out_of_scope = $nt_out_of_scope_index !== -1;

    if ($nt_has_out_of_scope)
    {
        # Insert-Position: rueckwaerts von Out-of-Scope bis zur letzten
        # nicht-leeren Zeile; Bullet kommt DANACH (vor der Leerzeile, die
        # "## Out of scope" voraus geht).
        $nt_insert_index = $nt_out_of_scope_index;

        for ($nt_j = $nt_out_of_scope_index - 1; $nt_j >= 0; $nt_j--)
        {
            $nt_prev_line          = $nt_existing_lines[$nt_j];
            $nt_prev_line_is_blank = trim($nt_prev_line) === "";

            if (! $nt_prev_line_is_blank)
            {
                $nt_insert_index = $nt_j + 1;
                break;
            }
        }

        array_splice($nt_existing_lines, $nt_insert_index, 0, [$nt_bullet_line]);
    }
    else
    {
        # Edge case: kein "## Out of scope" vorhanden. Append am Ende, mit
        # eigener "## Tickets"-Section falls noetig.
        $nt_has_tickets_section = false;

        foreach ($nt_existing_lines as $nt_line)
        {
            $nt_line_is_tickets = str_starts_with(ltrim($nt_line), "## Tickets");

            if ($nt_line_is_tickets)
            {
                $nt_has_tickets_section = true;
                break;
            }
        }

        if (! $nt_has_tickets_section)
        {
            $nt_existing_lines[] = "\n";
            $nt_existing_lines[] = "## Tickets\n";
        }

        $nt_existing_lines[] = $nt_bullet_line;
    }

    $nt_new_milestone_md = implode("", $nt_existing_lines);

    # Atomar schreiben: tmp + rename.
    $nt_tmp_abs = $nt_milestone_md_abs . ".tmp." . bin2hex(random_bytes(4));

    $nt_tmp_write_bytes = @file_put_contents($nt_tmp_abs, $nt_new_milestone_md);

    $nt_tmp_write_failed = $nt_tmp_write_bytes === false;

    if ($nt_tmp_write_failed)
    {
        app::error_log("pm.php new_ticket milestone.md tmp write failed: " . $nt_milestone_md_abs);

        if (is_file($nt_tmp_abs))
        {
            @unlink($nt_tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "milestone.md konnte nicht geschrieben werden.",
        ]));
    }

    $nt_rename_ok = @rename($nt_tmp_abs, $nt_milestone_md_abs);

    if (! $nt_rename_ok)
    {
        app::error_log("pm.php new_ticket milestone.md rename failed: " . $nt_milestone_md_abs);

        if (is_file($nt_tmp_abs))
        {
            @unlink($nt_tmp_abs);
        }

        http_response_code(500);
        exit(json_encode([
            "ok"      => false,
            "message" => "milestone.md Rename fehlgeschlagen.",
        ]));
    }

    app::error_log("pm.php new_ticket: " . $nt_file_rel);

    exit(json_encode([
        "ok"   => true,
        "path" => $nt_file_rel,
        "slug" => $nt_file_slug,
    ]));
}

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
    //     Edit ausserhalb `archive/`). Diese Schicht greift VOR jeder
    //     Whitelist; M014/0006 macht den Archive-Schutz zur expliziten
    //     Doppelschicht.
    //   - Pfad-Whitelist (M014/0001): Save akzeptiert genau dann, wenn
    //     `app::pm_write_kind($path)` !== "" ODER
    //     `app::pm_path_kind_legacy($path)` !== "".
    //       * pm_write_kind klassifiziert die neuen Info-Schreibziele:
    //         pm/ideas/<slug>.md, pm/reports/NNNN-<slug>.md,
    //         pm/audits/<slug>.md, pm/terms/<slug>.md,
    //         pm/decisions/NNNN-<slug>.md.
    //       * pm_path_kind_legacy klassifiziert die bisher schon
    //         erlaubten Pfade: pm/milestones/<ms>/milestone.md,
    //         pm/milestones/<ms>/open/NNNN-<slug>.md,
    //         pm/bugs/open/NNNN-<slug>.md.
    //     Faellt der Pfad in keine der beiden Schichten -> 400.
    //   - Decision-Sonderregel (append-only, siehe
    //     pm/how-to/decisions.md): wenn pm_write_kind === "decision"
    //     UND die Zieldatei bereits existiert -> 409
    //     `Decision ist append-only.`. Supersede via neue
    //     pm/decisions/-Datei mit naechsthoeherer Nummer.
    //   - Parent-Verzeichnis muss existieren und innerhalb Repo-Root sein;
    //     die Zieldatei selbst darf neu sein.
    //   - Binary-Guard (M013/0001-Vorbild): erste 8 KB des Bodys werden
    //     auf `\0`-Bytes geprueft. Treffer -> 400
    //     `Binaere Inhalte nicht erlaubt.`. Verhindert, dass binaere
    //     Inhalte (z.B. versehentlich als Datei hochgeladen) Markdown-
    //     Files zerstoeren, ohne UTF-8-Validierung zu bauen.
    //   - Max 1 MB Body, sonst 413.
    //   - Schreiben atomar via tmp+rename.
    //   - Antwort: 200 + {ok:true, path, bytes} bei Erfolg,
    //     400/409/413/500 + {ok:false, message} bei Fehler.
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

    # Pfad-Whitelist (M014/0001): Save akzeptiert nur Pfade, die entweder
    # in die neue Info-Whitelist (`pm_write_kind`) oder in die bisher
    # geltende Legacy-Whitelist (`pm_path_kind_legacy`) fallen. Faellt der
    # Pfad in keine der beiden Schichten, lehnen wir ab. Decisions werden
    # weiter unten — nach dem parent-Check — auf Append-only geprueft.
    $save_kind        = app::pm_write_kind($save_raw_path);
    $save_legacy_kind = app::pm_path_kind_legacy($save_raw_path);

    $save_kind_is_info     = $save_kind !== "";
    $save_kind_is_legacy   = $save_legacy_kind !== "";
    $save_path_is_writable = $save_kind_is_info || $save_kind_is_legacy;

    if (! $save_path_is_writable)
    {
        app::error_log("pm.php save rejected path (whitelist): " . $save_raw_path);
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

    # Decision-Sonderregel (append-only): zweite POST auf eine bestehende
    # Decision-Datei wird hart abgelehnt. Supersede laeuft ueber das
    # Anlegen einer neuen pm/decisions/-Datei (siehe
    # pm/how-to/decisions.md).
    $save_kind_is_decision      = $save_kind === "decision";
    $save_target_abs_for_check  = $repo_root_abs . "/" . $save_raw_path;
    $save_decision_exists       = $save_kind_is_decision && file_exists($save_target_abs_for_check);

    if ($save_decision_exists)
    {
        app::error_log("pm.php save rejected decision overwrite: " . $save_raw_path);
        http_response_code(409);
        exit(json_encode([
            "ok"      => false,
            "message" => "Decision ist append-only.",
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

    # Binary-Guard (M013/0001-Vorbild): erste 8 KB des Bodys auf NUL-Bytes
    # pruefen — wenn vorhanden, lehnen wir ab. So vermeiden wir, dass
    # binaere Inhalte (z.B. versehentlicher Upload eines Bildes) die
    # Markdown-Datei zerstoeren, ohne dass wir UTF-8-Validierung bauen.
    $body_head         = substr($body, 0, 8192);
    $body_is_binary    = str_contains($body_head, "\0");

    if ($body_is_binary)
    {
        app::error_log("pm.php save body looks binary for: " . $save_raw_path);
        http_response_code(400);
        exit(json_encode([
            "ok"      => false,
            "message" => "Binaere Inhalte nicht erlaubt.",
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
