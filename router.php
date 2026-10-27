<?php
declare(strict_types=1);

/**
 * Infinity Blog - CLI Built-in Web Server Router
 * 
 * Used by run-infinity-blog.bat (php -S localhost:8000 router.php).
 * Allows the entire application (static frontend + REST API backend)
 * to run with 1 click without requiring Apache mod_rewrite.
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$filePath = __DIR__ . $uri;

// 1. If it's an existing static file (CSS, JS, images, HTML files), let PHP serve it directly
if ($uri !== '/' && file_exists($filePath) && !is_dir($filePath)) {
    return false; // Return false so PHP built-in server serves the file directly
}

// 2. If it's an API request, dispatch directly to api/v1/index.php
if (str_starts_with($uri, '/api/v1') || $uri === '/api/v1') {
    require __DIR__ . '/api/v1/index.php';
    return true;
}

// 3. If accessing root or a directory, serve index.html
if ($uri === '/' || is_dir($filePath)) {
    $indexFile = rtrim($filePath, '/') . '/index.html';
    if (file_exists($indexFile)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexFile);
        return true;
    }
}

// 4. Fallback for html requests without extension (e.g. /posts -> /posts.html)
if (file_exists($filePath . '.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile($filePath . '.html');
    return true;
}

// Default 404
http_response_code(404);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'success' => false,
    'error' => [
        'code' => 'NOT_FOUND',
        'message' => 'The requested resource was not found on this server.'
    ]
], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
return true;
