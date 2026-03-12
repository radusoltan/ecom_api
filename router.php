<?php

// Router script for PHP built-in server
// Suppresses deprecation notices that flood the server's output buffer
// and block response delivery on the single-threaded built-in server.

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Serve static files directly
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__.'/public'.$path;

if ('/' !== $path && is_file($file)) {
    return false;
}

// Forward to Symfony front controller
$_SERVER['SCRIPT_FILENAME'] = __DIR__.'/public/index.php';
require __DIR__.'/public/index.php';
