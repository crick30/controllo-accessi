<?php
spl_autoload_register(function (string $class): void {
    $baseDir = __DIR__ . '/src/';
    $path = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

$configData = include __DIR__ . '/config.php';

return new Config\AppConfig($configData);
