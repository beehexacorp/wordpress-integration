<?php
/**
 * Router script for `php -S`: a fake HexaSync endpoint.
 * Appends every request as one JSON line to /data/requests.jsonl and answers
 * with the status code stored in /data/status (default 200).
 */
$body    = file_get_contents('php://input');
$headers = function_exists('getallheaders') ? getallheaders() : [];

$line = json_encode([
    'ts'      => microtime(true),
    'method'  => $_SERVER['REQUEST_METHOD'] ?? '',
    'path'    => $_SERVER['REQUEST_URI'] ?? '',
    'headers' => $headers,
    'body'    => $body,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
file_put_contents('/data/requests.jsonl', $line . "\n", FILE_APPEND | LOCK_EX);

$status = (int) trim((string) @file_get_contents('/data/status'));
if ($status < 100) {
    $status = 200;
}
http_response_code($status);
if ($status >= 300 && $status < 400) {
    header('Location: http://hxtest-fakehs:8080/redirected/');
}
header('Content-Type: application/json');
echo json_encode(['ok' => $status < 400]);
