<?php

// CHANGED: load classes before starting the session because PHP now
// needs our custom database session handler.
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/autoload.php';

// ADDED: use shared TiDB session storage instead of local /tmp files.
$sessionHandler = new framework\DatabaseSessionHandler();

session_set_save_handler(
    $sessionHandler,
    true
);

// ADDED: make the browser cookie unavailable to JavaScript.
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

// CHANGED: session data will now be read from/written to TiDB.
session_start();

// Existing application startup.
$router = new framework\Router();
$app = new framework\Application($router);
$app->run();