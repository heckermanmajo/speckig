<?php

declare(strict_types=1);

// @spec
// CLI-Einstieg fuer den Spec-Parser.
// Aufruf: php app/_share/spec_parser/index.php <pfad>
// Gibt das Parser-Schema als JSON auf STDOUT aus.
// Exit-Codes:
//   0 — Datei wurde erfolgreich an einen Sprach-Parser dispatcht.
//   1 — Aufruf-Fehler (kein Pfad, Datei existiert nicht).
//   2 — Vendor-Blacklist oder unsupported language.
// JSON-Form siehe app/_share/spec_parser/README.md.
// @end-spec

namespace _share\spec_parser;

require_once __DIR__ . "/spec_parser.php";

$invoked_via_cli = PHP_SAPI === "cli";

if ( ! $invoked_via_cli)
{
    http_response_code(403);
    echo json_encode(["error" => "cli only"]);
    exit(1);
}

$path_argument_missing = ! isset($argv[1]) || $argv[1] === "";

if ($path_argument_missing)
{
    fwrite(STDERR, "usage: php " . ($argv[0] ?? "index.php") . " <path>\n");
    exit(1);
}

$path = $argv[1];

$result = spec_parser::parse($path);

echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
echo "\n";

$result_has_error = isset($result["error"]);

exit($result_has_error ? 2 : 0);
