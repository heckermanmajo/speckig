<?php 

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

use _share\app;
use _share\html\document;

if (app::is_mobile()) app::redirect("/index_mobile.php");
app::enforce_login();

document::head();

?>

<main>

<h2>One User profile seite</h2>

<pre>
    - see another users profile
    - check if we are allowed to do this ...
</pre>

</main>
<?php

document::footer();
