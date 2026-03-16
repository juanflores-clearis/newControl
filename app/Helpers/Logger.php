<?php

/**
 * Escribe una línea de log simple con timestamp en logs/analyze_YYYY-MM-DD.log
 */
function logApiRawResponse(string $service, string $url, array $data): void {
    $host = parse_url('https://' . $url, PHP_URL_HOST) ?? preg_replace('/[^a-z0-9]/i', '_', $url);
    $timestamp = date('Y-m-d_His');
    $dir = __DIR__ . '/../../logs/' . $service;

    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $filename = $dir . "/{$timestamp}__{$host}.json";

    file_put_contents($filename, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}


/**
 * Guarda en logs/virustotal_YYYY-MM-DD.json o sucuri_YYYY-MM-DD.json
 * una entrada JSON por línea con la respuesta completa y timestamp.
 */
function appendApiLog(string $service, string $url, array $data): void {
    $dir = __DIR__ . '/../../logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $file = $dir . '/' . $service . '_' . date('Y-m-d') . '.json';

    $entry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'url' => $url,
        'response' => $data
    ];

    // Guardar como JSON por línea (más fácil para parsing)
    file_put_contents($file, json_encode($entry, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
}

function logToCustomFile(string $message): void {
    $logDir = __DIR__ . '/../../logs';

    if (!is_dir($logDir)) {
        mkdir($logDir, 0775, true);
    }

    $date = date('Y-m-d');
    $timestamp = date('Y-m-d H:i:s');

    $logFile = $logDir . "/analyze_$date.log";
    $entry = "[$timestamp] $message" . PHP_EOL;

    file_put_contents($logFile, $entry, FILE_APPEND);
}
