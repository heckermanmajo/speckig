<?php 

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

use _share\app;
use _share\html\document;

if (!app::is_mobile()) app::redirect("/index.php");
app::enforce_login();

document::head();

document::footer();
