<?php
declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'InfinityBlog\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = __DIR__ . '/app/' . str_replace('\\', DIRECTORY_SEPARATOR, $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
