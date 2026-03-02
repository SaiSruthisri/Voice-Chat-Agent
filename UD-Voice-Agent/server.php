<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

/*
|--------------------------------------------------------------------------
| Redirect root "/" to frontend
|--------------------------------------------------------------------------
*/
if ($uri === '/') {
    header('Location: /frontend/chat.php?brandId=default');
    exit;
}

/*
|--------------------------------------------------------------------------
| API Routing
|--------------------------------------------------------------------------
*/
if (preg_match('#^/api/#', $uri)) {
    require __DIR__ . '/backend/index.php';
    return true;
}

/*
|--------------------------------------------------------------------------
| Assets Routing
|--------------------------------------------------------------------------
*/
if (preg_match('#^/assets/(.+)$#', $uri, $matches)) {
    $assetName = basename($matches[1]);
    $assetRoots = [
        realpath(__DIR__ . '/assets'),
        realpath(dirname(__DIR__) . '/assets'),
    ];

    foreach ($assetRoots as $assetsRoot) {
        if (!$assetsRoot) {
            continue;
        }

        $assetPath = realpath($assetsRoot . DIRECTORY_SEPARATOR . $assetName);
        if ($assetPath && str_starts_with($assetPath, $assetsRoot) && is_file($assetPath)) {
            $mimeType = mime_content_type($assetPath) ?: 'application/octet-stream';
            header('Content-Type: ' . $mimeType);
            readfile($assetPath);
            return true;
        }
    }

    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Asset not found';
    return true;
}

/*
|--------------------------------------------------------------------------
| Let PHP serve existing files (frontend pages)
|--------------------------------------------------------------------------
*/
return false;