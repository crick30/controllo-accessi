<?php

use Config\AppConfig;

function isPost(array $server): bool
{
    return ($server['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function activeTheme(AppConfig $config): string
{
    if ($config->themeMode === 'dark') {
        return 'dark';
    }

    if ($config->themeMode === 'light') {
        return 'light';
    }

    $hour = (int) date('G');

    return ($hour >= $config->lightStartHour && $hour < $config->lightEndHour) ? 'light' : 'dark';
}

function streamCsv(string $filename, array $headers, array $rows): void
{
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    if ($out === false) {
        throw new RuntimeException('Unable to open output stream for CSV export.');
    }

    fputcsv($out, $headers);

    foreach ($rows as $row) {
        fputcsv($out, $row);
    }

    fclose($out);
}
