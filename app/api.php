<?php

# --- bootstrapping ---

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

use _share\Action;
use _share\app;


# --- inputs ---

$raw_action_name = $_REQUEST["action"] ?? "";
$submitted_csrf_token = $_REQUEST["csrf_token"] ?? "";


# --- guard: action param present ---

if ($raw_action_name === "")
{
    app::error_log("api.php called without action param");
    exit(json_encode([
        "err" => true,
        "message" => "missing action param",
    ]));
}


# --- guard: csrf ---
# Token is generated in _share/init.php and lives in $_SESSION.
# Compared with hash_equals to avoid timing attacks.

$session_csrf_token = $_SESSION["csrf_token"] ?? "";

$csrf_token_is_valid =
    $submitted_csrf_token !== ""
    && $session_csrf_token !== ""
    && hash_equals($session_csrf_token, $submitted_csrf_token);

if (!$csrf_token_is_valid)
{
    app::error_log("api.php csrf mismatch for action: " . $raw_action_name);
    exit(json_encode([
        "err" => true,
        "message" => "csrf token invalid",
    ]));
}


# --- guard: action class name shape ---
# The classname is user-controlled and gets fed into the autoloader,
# which translates "\" to "/" and requires the resulting path.
# Without this whitelist a request like action=../../etc/passwd or
# action=..\..\something would happily try to include arbitrary files.
# Allowed: namespaced class names only, e.g. "user\actions\LoginAction".

$action_name_pattern = '/^[A-Za-z_][A-Za-z0-9_]*(\\\\[A-Za-z_][A-Za-z0-9_]*)+$/';

$action_name_has_valid_shape =
    is_string($raw_action_name)
    && preg_match($action_name_pattern, $raw_action_name) === 1;

if (!$action_name_has_valid_shape)
{
    app::error_log("api.php rejected malformed action name: " . $raw_action_name);
    exit(json_encode([
        "err" => true,
        "message" => "invalid action name",
    ]));
}


# --- strip transport-level fields from $_REQUEST ---
# action and csrf_token are part of the AJAX transport, not part of
# the Action's input contract. The Action::bindInputToParameters
# logic rejects unknown fields, so we have to remove them here
# before dispatch. Same for $_POST/$_GET to be safe — the Action
# layer reads from $_REQUEST but other code paths might inspect
# either of those.

unset($_REQUEST["action"], $_REQUEST["csrf_token"]);
unset($_POST["action"],    $_POST["csrf_token"]);
unset($_GET["action"],     $_GET["csrf_token"]);


# --- dispatch ---

try
{
    if (!class_exists($raw_action_name))
    {
        app::error_log("api.php unknown action class: " . $raw_action_name);
        exit(json_encode([
            "err" => true,
            "message" => "unknown action",
        ]));
    }

    if (!is_subclass_of($raw_action_name, Action::class))
    {
        app::error_log("api.php class is not an Action: " . $raw_action_name);
        exit(json_encode([
            "err" => true,
            "message" => "not an action",
        ]));
    }

    $raw_action_name::execute_as_request_and_dump_json_and_exit();
}
catch (Throwable $t)
{
    app::error_log("api.php uncaught throwable: " . $t->getMessage());
    exit(json_encode([
        "err" => true,
        "message" => "internal error",
    ]));
}
