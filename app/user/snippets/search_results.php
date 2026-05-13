<?php

# --- bootstrapping ---
# Snippet that returns an <ul> of user search hits as raw HTML.
# Loaded via load_snippet_into(...) from a parent dialog.
#
# Each hit calls a parent-defined select_user_for_picker(id, label)
# on click. The parent snippet must define that function.

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

use _share\app;
use _share\db;
use user\data\User;


# --- guards ---
# Admin-only: this exposes a searchable list of users.

app::enforce_login();
app::enforce_plattform_admin();


# --- inputs ---

$raw_query = $_GET["q"] ?? "";
$raw_query = is_string($raw_query) ? trim($raw_query) : "";


# --- response headers ---

header("Content-Type: text/html; charset=utf-8");


# --- empty query: render hint, no DB hit ---

if ($raw_query === "")
{
    ?>
    <ul class="search-results search-results-empty">
        <li class="search-results-hint">Tippe um zu suchen&hellip;</li>
    </ul>
    <?php
    exit;
}


# --- query ---
# LIKE on username and email. Bound parameter — no SQL injection.
# Limit hard-coded; this is a picker, not a report.

$like_pattern = "%" . $raw_query . "%";

$matching_users = db::select(
    User::class,
    "SELECT * FROM User
        WHERE username LIKE :pattern
           OR email    LIKE :pattern
        ORDER BY username ASC
        LIMIT 20",
    ["pattern" => $like_pattern]
);


# --- render ---

if (count($matching_users) === 0)
{
    ?>
    <ul class="search-results search-results-empty">
        <li class="search-results-hint">Keine Treffer.</li>
    </ul>
    <?php
    exit;
}

?>
<ul class="search-results">
    <?php foreach ($matching_users as $one_user): ?>
        <?php
            $display_label =
                $one_user->username
                . ($one_user->email !== "" ? " (" . $one_user->email . ")" : "");
        ?>
        <?php
            # We embed a JS literal inside an HTML attribute. The
            # JSON_HEX_* flags strip raw quotes/tags from the JSON
            # itself; htmlspecialchars then escapes the outer
            # quotes so the onclick attribute is well-formed.
            $js_label_literal = json_encode(
                $display_label,
                JSON_UNESCAPED_UNICODE
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            );
            $onclick_safe = htmlspecialchars(
                "select_user_for_picker(" . (int)$one_user->id . ", " . $js_label_literal . ")",
                ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5,
                "UTF-8"
            );
        ?>
        <li
            class="search-result-item"
            data-id="<?= (int)$one_user->id ?>"
            onclick="<?= $onclick_safe ?>"
        >
            <?= app::escape($display_label) ?>
        </li>
    <?php endforeach; ?>
</ul>
