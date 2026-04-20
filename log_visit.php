<?php
/**
 * Nimmt Besuche per POST (JSON) entgegen und schreibt eine Zeile nach private/visits.log
 * Nur unter https/http nutzbar, nicht bei reinem file://-Aufruf.
 */
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'json']);
    exit;
}

$page = isset($data['page']) ? (string) $data['page'] : '';
$page = str_replace(["\r", "\n", "\0"], '', $page);
if (strlen($page) > 500) {
    $page = substr($page, 0, 500);
}

$visitorId = isset($data['visitorId']) ? (string) $data['visitorId'] : '';
$visitorId = preg_replace('/[^a-zA-Z0-9\-]/', '', $visitorId);
if (strlen($visitorId) > 80) {
    $visitorId = substr($visitorId, 0, 80);
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
$ip = preg_replace('/[^0-9a-fA-F.:]/', '', $ip);
if (strlen($ip) > 45) {
    $ip = substr($ip, 0, 45);
}

$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : '';
$ua = substr($ua, 0, 300);

$line = json_encode([
    't' => gmdate('c'),
    'ip' => $ip,
    'ua' => $ua,
    'page' => $page,
    'visitor' => $visitorId,
], JSON_UNESCAPED_UNICODE) . "\n";

$dir = __DIR__ . '/private';
if (!is_dir($dir)) {
    if (!@mkdir($dir, 0755, true)) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'mkdir']);
        exit;
    }
}

$logFile = $dir . '/visits.log';
$result = @file_put_contents($logFile, $line, FILE_APPEND | LOCK_EX);
if ($result === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'write']);
    exit;
}

echo json_encode(['ok' => true]);
