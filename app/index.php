<?php

declare(strict_types=1);

include $_SERVER["DOCUMENT_ROOT"] . "/_share/init.php";

# Minimal Speckig-Skelett for M001.
# Tree-View (0006) und Markdown-Render (0007) folgen.
# Login-/Nav-Code aus document::head() ist absichtlich nicht eingebunden:
# das Ticket fordert "ohne Login-Logik", und document::head() rendert
# die globale Nav mit Login-Verweisen. Eigenes Skelett ist ehrlicher.

?>
<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>speckig</title>
</head>
<body>
<main>
    <h1>speckig</h1>
</main>
</body>
</html>
