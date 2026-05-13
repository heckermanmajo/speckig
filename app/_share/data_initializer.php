<?php

# CLI-only database initializer. Creates / migrates the schema for
# every registered DataClass. Idempotent: safe to run repeatedly.
#
# Usage:
#   php app/_share/data_initializer.php


# --- guard: refuse to run outside of CLI ---

$is_cli = PHP_SAPI === "cli";

if ( ! $is_cli )
{
    http_response_code(403);
    echo "data_initializer.php is CLI-only.\n";
    exit(1);
}


# --- autoloader: resolve classes relative to the app/ directory ---
#
# init.php uses $_SERVER["DOCUMENT_ROOT"] for autoloading, which is
# empty under CLI. We register a CLI-flavoured autoloader that walks
# up from this file to the app root.

$app_root = dirname(__DIR__);

spl_autoload_register(
    function ($class) use ($app_root)
    {
        $relative_path = str_replace('\\', '/', $class) . '.php';
        $full_path = $app_root . '/' . $relative_path;

        if ( ! is_file($full_path) )
        {
            fwrite(STDERR, "autoload failed: class {$class} not found at {$full_path}\n");
            return;
        }

        require $full_path;
    }
);


# --- registry: every DataClass that should have a table ---

use _share\db;
use user\data\User;

$data_classes = [
    User::class,
];


# --- run: create / update each table, report status per line ---

$failed_classes = [];

foreach ($data_classes as $data_class)
{
    echo "creating table {$data_class}... ";

    try
    {
        db::create_and_update_table($data_class);
        echo "ok\n";
    }
    catch (Throwable $t)
    {
        echo "FAILED\n";
        fwrite(STDERR, "  {$t->getMessage()}\n");
        $failed_classes[] = $data_class;
    }
}


# --- summary + exit code ---

$failure_count = count($failed_classes);

if ($failure_count > 0)
{
    echo "done with {$failure_count} failure(s).\n";
    exit(1);
}

echo "done.\n";
exit(0);
