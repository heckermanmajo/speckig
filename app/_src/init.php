<?php

use _share\db;
use user\data\User;

spl_autoload_register(
    function ($class)
    {
        require $_SERVER["DOCUMENT_ROOT"] . "/" . str_replace('\\', '/', $class) . '.php';
    }
);

ob_start();

date_default_timezone_set('Europe/Berlin');

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

session_start();

if (empty($_SESSION['csrf_token']))
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$raw = file_get_contents('php://input');
if ($raw !== '' && $raw !== false)
{
    $decoded = json_decode($raw, true);
    if (is_array($decoded))
    {
        $_REQUEST = array_merge($_REQUEST, $decoded);
    }
}

if ( ! db::get_by_id( User::class, 1 ) )
{
    $user = new User();
    $user->username = "admin";
    $user->password = password_hash( "admin", PASSWORD_DEFAULT );
    $user->is_admin = 1;
    db::save( $user );
}