<?php
// This file allows us to emulate Apache's "mod_rewrite" functionality from the built-in PHP web server.
// This provides pretty URLs for your application.

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    return false;
}

require_once __DIR__ . '/index.php';
