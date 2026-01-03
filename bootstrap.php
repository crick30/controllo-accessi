<?php
spl_autoload_register(function (string $class): void {
    $baseDir = __DIR__ . '/src/';
    $path = $baseDir . str_replace('\\', '/', $class) . '.php';
    if (file_exists($path)) {
        require $path;
    }
});

function loadEnvFile(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);

        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        if (!str_contains($trimmed, '=')) {
            continue;
        }

        [$key, $value] = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);

        if ($key === '') {
            continue;
        }

        if (getenv($key) !== false && getenv($key) !== '') {
            continue;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

$baseEnvFile = __DIR__ . '/.env';
$customEnvFile = getenv('APP_ENV_FILE') ?: null;

if ($customEnvFile) {
    $path = str_starts_with($customEnvFile, '/')
        ? $customEnvFile
        : __DIR__ . '/' . ltrim($customEnvFile, '/');
    loadEnvFile($path);
} else {
    loadEnvFile($baseEnvFile);
}

$profile = getenv('APP_ENV_PROFILE') ?: getenv('APP_ENV') ?: null;
if ($profile) {
    loadEnvFile(__DIR__ . '/.env.' . $profile);
}

$configData = include __DIR__ . '/config.php';

return new Config\AppConfig($configData);
