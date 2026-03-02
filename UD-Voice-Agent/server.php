<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (preg_match('#^/api/#', $uri)) {
    require __DIR__ . '/backend/index.php';
    return;
}

if (preg_match('#^/assets/(.+)$#', $uri, $matches)) {
    $assetName = basename($matches[1]);
    $assetPath = realpath(__DIR__ . '/../assets/' . $assetName);
    $assetsRoot = realpath(__DIR__ . '/../assets');

    if ($assetPath && $assetsRoot && str_starts_with($assetPath, $assetsRoot) && is_file($assetPath)) {
        $mimeType = mime_content_type($assetPath) ?: 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        readfile($assetPath);
        return true;
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset not found';
    return true;
}

return false;
