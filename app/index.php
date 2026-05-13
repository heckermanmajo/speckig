<?php

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

use _share\app;
use _share\html\document;

if (app::is_mobile()) app::redirect("/index_mobile.php");
if (app::somebody_is_logged_in()) app::redirect("/dash/");

document::head();

?>

<main>

    <h1>Login</h1>

    <form data-action="user.actions.LoginAction" id="login_form">

        <label>
            Username <br>
            <input type="text" name="username" autocomplete="username" required>
        </label>

        <br>

        <label>
            Password<br>
            <input type="password" name="password" autocomplete="current-password" required>
        </label>

        <br>

        <button type="submit">Login</button>

        <p id="login_form_error_message" hidden></p>

    </form>

</main>

<script>

    // wire login form to /api.php via the shared ajax helper

    document.addEventListener("DOMContentLoaded", function ()
    {
        const login_form_element         = document.getElementById("login_form");
        const login_form_error_paragraph = document.getElementById("login_form_error_message");

        handle_form_submit(login_form_element, {

            onSuccess: function (response_data, full_response)
            {
                login_form_error_paragraph.hidden      = true;
                login_form_error_paragraph.textContent = "";

                // success path — server has set the session, jump to dashboard
                window.location.href = "/dash/";
            },

            onError: function (error_message, full_response)
            {
                login_form_error_paragraph.hidden      = false;
                login_form_error_paragraph.textContent = error_message;
            },

        });
    });

</script>

<?php

document::footer();
