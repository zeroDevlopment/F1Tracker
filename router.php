<?php
// If the requested file exists, serve it as-is
$path = __DIR__ . parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
if (file_exists($path) && !is_dir($path)) {
    return false; // Let PHP serve the static file
}

// Otherwise, serve index.php
require_once __DIR__ . '/index.php';
