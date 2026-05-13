<?php

namespace _share\html;

use _share\app;
use _share\db;
use user\data\User;

class document
{
    static function head()
    {
        # csrf token from session, generated in _share/init.php
        $csrf_token_for_meta = $_SESSION["csrf_token"] ?? "";

        # nav data: who is logged in, and are they an admin?
        # Pulled here so the nav renders uniformly on every page.
        # No throws if there is no session — the nav just falls
        # back to a "not logged in" state.

        $logged_in_user_or_null = null;

        if (app::somebody_is_logged_in())
        {
            $logged_in_user_or_null = db::get_by_id(
                User::class,
                app::get_current_user_id()
            );
        }

        $user_is_logged_in   = $logged_in_user_or_null !== null;
        $user_is_admin       = $user_is_logged_in && $logged_in_user_or_null->is_admin === 1;
        $current_username    = $user_is_logged_in ? $logged_in_user_or_null->username : "";

        ?>
        <!doctype html>
        <html>
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="csrf-token" content="<?= app::escape($csrf_token_for_meta) ?>">
            <link rel="stylesheet" href="/sharecss/">
            <script src="/sharejs/" defer></script>
        </head>
        <body>
        <nav class="global-nav">
            <a href="/" class="global-nav-brand">community</a>

            <?php if ($user_is_logged_in): ?>
                <a href="/dash/">Dashboard</a>

                <?php if ($user_is_admin): ?>
                    <a href="/admin/p/dash/" class="global-nav-admin">Admin</a>
                <?php endif; ?>

                <span class="global-nav-user">
                    eingeloggt als <strong><?= app::escape($current_username) ?></strong>
                </span>
            <?php else: ?>
                <a href="/">Login</a>
            <?php endif; ?>
        </nav>
        <?php
    }

    static function footer()
    {
        # global dialog slot.
        # Filled by /sharejs/snippet.js (open_dialog_snippet) at runtime.
        # Lives once per page, regardless of how many places open dialogs.
        ?>
        <dialog id="global-dialog">
            <div id="global-dialog-content"></div>
        </dialog>
        </body>
        </html>
        <?php
    }


}
