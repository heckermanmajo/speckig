<?php

declare(strict_types=1);

// @spec
// CLI-Test-Runner fuer den Spec-Parser (M005/0004, erweitert in M007/0003).
// Iteriert ueber app/_share/spec_parser/tests/fixtures/php/*.php,
// app/_share/spec_parser/tests/fixtures/js/*.js und
// app/_share/spec_parser/tests/fixtures/nim/*.nim, ruft fuer jede Fixture
// spec_parser::parse($path) und vergleicht das Ergebnis per Deep-Compare
// (json_decode + rekursiv) mit der zugehoerigen <name>.expected.json.
// Druckt PASS/FAIL pro Fixture und am Ende "X/Y passed" plus eine
// Pro-Sprache-Zusammenfassung "php: X/Y", "js: X/Y", "nim: X/Y".
// Zwei Sonder-Tests sind hartkodiert (ohne Fixture-Files):
//   - Vendor-Blacklist: spec_parser::parse("app/_share/vendor/anything.php")
//     muss `error: "vendor code not parsed"` liefern.
//   - Unsupported-Language: spec_parser::parse("foo.css") muss
//     `error: "unsupported language"` liefern.
// Exit-Code 0 wenn alle gruen, 1 sonst. CLI-only — bei SAPI != cli sofort 1.
// Kein Regex (Decision 0006).
// @end-spec

namespace _share\spec_parser\tests;

use _share\spec_parser\spec_parser;

$invoked_via_cli = PHP_SAPI === "cli";

if ( ! $invoked_via_cli)
{
    http_response_code(403);
    echo "cli only\n";
    exit(1);
}

require_once __DIR__ . "/../spec_parser.php";

$repo_root = realpath(__DIR__ . "/../../../..");
$fixture_root = __DIR__ . "/fixtures";

// @spec
// Sammelt PASS/FAIL-Zaehler und gibt am Ende die Zusammenfassung aus.
// Klein gehalten: globaler Zustand reicht fuer einen einzelnen Lauf.
// $lang_counts speichert pro Sprach-Tag (php/js/nim) ein Tuple
// [total, passed], damit am Ende eine Pro-Sprache-Zusammenfassung
// gedruckt werden kann. Synthetische Tests laufen ohne Sprach-Tag.
// @end-spec
$total_count  = 0;
$passed_count = 0;
$failures     = [];
$lang_counts  = [];

// @spec
// Vergleicht Erwartung und tatsaechlichen Wert tief.
// Sammelt bis zu drei Pfad-Differenzen als Strings ("symbols[0].name: expected X, got Y").
// Liefert leeres Array wenn die beiden Werte gleich sind.
// Kein Regex — reine Typ-/Wert-Pruefung mit ===.
// @end-spec
function deep_diff($expected, $actual, string $path = "", int $max_diffs = 3): array
{
    $diffs = [];

    $compare_recursive = function ($exp, $act, string $cur_path) use (&$compare_recursive, &$diffs, $max_diffs)
    {
        if (count($diffs) >= $max_diffs)
        {
            return;
        }

        $expected_is_array = is_array($exp);
        $actual_is_array   = is_array($act);

        if ($expected_is_array !== $actual_is_array)
        {
            $diffs[] = $cur_path . ": expected " . encode_for_message($exp)
                . ", got " . encode_for_message($act);
            return;
        }

        if ( ! $expected_is_array)
        {
            // Scalar comparison.
            if ($exp !== $act)
            {
                $diffs[] = $cur_path . ": expected " . encode_for_message($exp)
                    . ", got " . encode_for_message($act);
            }
            return;
        }

        // Both arrays. Decide list vs assoc by checking keys.
        $exp_is_list = array_is_list($exp);
        $act_is_list = array_is_list($act);

        if ($exp_is_list && $act_is_list)
        {
            $exp_n = count($exp);
            $act_n = count($act);

            if ($exp_n !== $act_n)
            {
                $diffs[] = $cur_path . ".length: expected " . $exp_n . ", got " . $act_n;
                // also continue per-element so we surface useful diffs
            }

            $shared = min($exp_n, $act_n);
            for ($i = 0; $i < $shared; $i++)
            {
                if (count($diffs) >= $max_diffs) { return; }
                $compare_recursive($exp[$i], $act[$i], $cur_path . "[" . $i . "]");
            }
            return;
        }

        // Treat as associative: check key presence each direction.
        $exp_keys = array_keys($exp);
        $act_keys = array_keys($act);

        foreach ($exp_keys as $k)
        {
            if (count($diffs) >= $max_diffs) { return; }
            if ( ! array_key_exists($k, $act))
            {
                $diffs[] = $cur_path . "." . $k . ": expected key missing in actual";
                continue;
            }
            $sub_path = $cur_path === "" ? (string) $k : $cur_path . "." . $k;
            $compare_recursive($exp[$k], $act[$k], $sub_path);
        }

        foreach ($act_keys as $k)
        {
            if (count($diffs) >= $max_diffs) { return; }
            if ( ! array_key_exists($k, $exp))
            {
                $diffs[] = $cur_path . "." . $k . ": unexpected key in actual";
            }
        }
    };

    $compare_recursive($expected, $actual, $path);
    return $diffs;
}

// @spec
// Kompakte String-Repraesentation eines Werts fuer Diff-Meldungen.
// Arrays/Objekte: json_encode; Skalare: var_export-aehnlich.
// Begrenzt Laenge, damit lange Strings die Meldung nicht sprengen.
// @end-spec
function encode_for_message($value): string
{
    if (is_string($value))
    {
        $short = $value;
        if (strlen($short) > 80)
        {
            $short = substr($short, 0, 77) . "...";
        }
        return "\"" . $short . "\"";
    }
    if (is_bool($value))    { return $value ? "true" : "false"; }
    if (is_null($value))    { return "null"; }
    if (is_int($value) || is_float($value)) { return (string) $value; }

    $encoded = json_encode($value, JSON_UNESCAPED_SLASHES);
    if ($encoded === false) { return "<unencodable>"; }
    if (strlen($encoded) > 80)
    {
        $encoded = substr($encoded, 0, 77) . "...";
    }
    return $encoded;
}

// @spec
// Laeuft eine einzelne Fixture: parsed die Source-Datei, vergleicht mit
// der zugehoerigen .expected.json, druckt PASS/FAIL und aktualisiert die
// globalen Zaehler. $language ist ein Tag (z.B. "php"/"js"/"nim"),
// das fuer die Pro-Sprache-Zusammenfassung am Ende benutzt wird.
// @end-spec
function run_fixture(string $source_path, string $expected_path, string $language): void
{
    global $total_count, $passed_count, $failures, $lang_counts;

    $total_count++;
    if ( ! isset($lang_counts[$language]))
    {
        $lang_counts[$language] = ["total" => 0, "passed" => 0];
    }
    $lang_counts[$language]["total"]++;

    $expected_raw = @file_get_contents($expected_path);
    if ($expected_raw === false)
    {
        echo "FAIL " . $source_path . ": expected file not readable (" . $expected_path . ")\n";
        $failures[] = $source_path;
        return;
    }

    $expected = json_decode($expected_raw, true);
    if ($expected === null && trim($expected_raw) !== "null")
    {
        echo "FAIL " . $source_path . ": expected file is invalid JSON (" . $expected_path . ")\n";
        $failures[] = $source_path;
        return;
    }

    $actual = spec_parser::parse($source_path);

    $diffs = deep_diff($expected, $actual);

    if (empty($diffs))
    {
        echo "PASS " . $source_path . "\n";
        $passed_count++;
        $lang_counts[$language]["passed"]++;
        return;
    }

    echo "FAIL " . $source_path . ":\n";
    foreach ($diffs as $d)
    {
        echo "  " . $d . "\n";
    }
    $failures[] = $source_path;
}

// @spec
// Laeuft einen synthetischen Test: ruft spec_parser::parse mit einem
// gegebenen Eingabe-Pfad und prueft, dass das Ergebnis-Array die im
// $expected_subset uebergebenen Schluessel/Werte enthaelt (nicht mehr).
// PASS wenn alle Schluessel uebereinstimmen.
// @end-spec
function run_synthetic(string $name, string $input_path, array $expected_subset): void
{
    global $total_count, $passed_count, $failures;

    $total_count++;

    $actual = spec_parser::parse($input_path);

    $bad = [];
    foreach ($expected_subset as $k => $v)
    {
        if ( ! array_key_exists($k, $actual))
        {
            $bad[] = $k . ": missing";
            continue;
        }
        if ($actual[$k] !== $v)
        {
            $bad[] = $k . ": expected " . encode_for_message($v)
                . ", got " . encode_for_message($actual[$k]);
        }
    }

    if (empty($bad))
    {
        echo "PASS " . $name . "\n";
        $passed_count++;
        return;
    }

    echo "FAIL " . $name . ":\n";
    foreach ($bad as $b)
    {
        echo "  " . $b . "\n";
    }
    $failures[] = $name;
}

// @spec
// Iteriert ueber alle Fixture-Source-Dateien (.php + .js + .nim) im fixture
// root und ruft fuer jede run_fixture(). Sortiert die Liste, damit die
// Ausgabe reproduzierbar ist. Pro Sprach-Verzeichnis wird ein Sprach-Tag
// mitgegeben (php/js/nim) — der landet in der Pro-Sprache-Zusammenfassung.
// @end-spec
function run_all_fixtures(string $fixture_root): void
{
    $php_glob = glob($fixture_root . "/php/*.php");
    $js_glob  = glob($fixture_root . "/js/*.js");
    $nim_glob = glob($fixture_root . "/nim/*.nim");

    if ($php_glob === false) { $php_glob = []; }
    if ($js_glob  === false) { $js_glob  = []; }
    if ($nim_glob === false) { $nim_glob = []; }

    sort($php_glob);
    sort($js_glob);
    sort($nim_glob);

    $groups = [
        ["lang" => "php", "ext" => ".php", "files" => $php_glob],
        ["lang" => "js",  "ext" => ".js",  "files" => $js_glob],
        ["lang" => "nim", "ext" => ".nim", "files" => $nim_glob],
    ];

    foreach ($groups as $group)
    {
        $ext     = $group["ext"];
        $lang    = $group["lang"];
        $ext_len = strlen($ext);

        foreach ($group["files"] as $abs_path)
        {
            $expected_path = substr($abs_path, 0, -$ext_len) . ".expected.json";

            // Fixtures call the parser with a repo-relative-ish path so the
            // resulting "file" field stays stable across machines.
            $repo_relative = derive_repo_relative_path($abs_path);

            run_fixture($repo_relative, $expected_path, $lang);
        }
    }
}

// @spec
// Wandelt einen absoluten Fixture-Pfad in den Repo-relativen Pfad um,
// damit das "file"-Feld im Parser-Output (und in expected.json) keinen
// Maschinen-spezifischen Anteil enthaelt. Verlasst sich auf die fixe
// Position der Fixture relativ zum Repo-Root.
// @end-spec
function derive_repo_relative_path(string $abs_path): string
{
    $marker = "/app/_share/spec_parser/tests/fixtures/";
    $pos    = strpos($abs_path, $marker);
    if ($pos === false)
    {
        // Fallback: hand back the absolute path; the fixture's expected.json
        // would then need to use this exact path. Should not happen in the
        // pinned layout.
        return $abs_path;
    }
    return "app/_share/spec_parser/tests/fixtures/" . substr($abs_path, $pos + strlen($marker));
}

echo "spec_parser fixture tests\n";
echo "=========================\n";

run_all_fixtures($fixture_root);

echo "\nsynthetic tests\n";
echo "---------------\n";

run_synthetic(
    "vendor blacklist rejects app/_share/vendor/* paths",
    "app/_share/vendor/anything.php",
    [
        "file"  => "app/_share/vendor/anything.php",
        "error" => "vendor code not parsed",
    ]
);

run_synthetic(
    "unsupported language rejects .css",
    "foo.css",
    [
        "file"      => "foo.css",
        "error"     => "unsupported language",
        "extension" => "css",
    ]
);

echo "\n";
echo $passed_count . "/" . $total_count . " passed\n";

if ( ! empty($lang_counts))
{
    // Stable order: php, js, nim, then anything else alphabetically.
    $preferred_order = ["php", "js", "nim"];
    $printed = [];
    foreach ($preferred_order as $lang)
    {
        if (isset($lang_counts[$lang]))
        {
            echo $lang . ": " . $lang_counts[$lang]["passed"]
                . "/" . $lang_counts[$lang]["total"] . "\n";
            $printed[$lang] = true;
        }
    }
    $other = array_keys(array_diff_key($lang_counts, $printed));
    sort($other);
    foreach ($other as $lang)
    {
        echo $lang . ": " . $lang_counts[$lang]["passed"]
            . "/" . $lang_counts[$lang]["total"] . "\n";
    }
}

if ($passed_count !== $total_count)
{
    echo "failures:\n";
    foreach ($failures as $f)
    {
        echo "  - " . $f . "\n";
    }
    exit(1);
}

exit(0);
