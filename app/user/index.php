<?php 

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

use _share\app;
use _share\html\document;

if (app::is_mobile()) app::redirect("/index_mobile.php");
app::enforce_login();

document::head();

?>

<main>

<h2>User seite</h2>

<pre>
    - see my profile and my profile settings
    - also one profiole
</pre>

</main>
<?php

document::footer();
