<?php
/**
 * Proxy: Browser → PHP → Ollama (localhost).
 * Ollama nicht öffentlich freigeben – nur über diesen Endpunkt.
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method']);
    exit;
}

/* === Konfiguration === */
$OLLAMA_URL = 'http://127.0.0.1:11434';
/* Leer lassen = erstes verfügbares Modell. Sonst z. B. 'llama3.2' oder 'qwen2.5:3b' */
$OLLAMA_MODEL = 'qwen2.5:0.5b';
$MAX_MESSAGE_CHARS = 800;
$MAX_HISTORY_MESSAGES = 12;
$RATE_LIMIT_SECONDS = 4;
$RATE_LIMIT_DIR = __DIR__ . '/private/chat_rate';
$REQUEST_TIMEOUT_SECONDS = 90;

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'json']);
    exit;
}

$message = isset($data['message']) ? trim((string) $data['message']) : '';
$message = str_replace("\0", '', $message);
if ($message === '' || mb_strlen($message) > $MAX_MESSAGE_CHARS) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'message']);
    exit;
}

$history = [];
if (isset($data['history']) && is_array($data['history'])) {
    $slice = array_slice($data['history'], -$MAX_HISTORY_MESSAGES);
    foreach ($slice as $item) {
        if (!is_array($item)) {
            continue;
        }
        $role = isset($item['role']) ? (string) $item['role'] : '';
        $content = isset($item['content']) ? trim((string) $item['content']) : '';
        $content = str_replace("\0", '', $content);
        if (($role !== 'user' && $role !== 'assistant') || $content === '') {
            continue;
        }
        if (mb_strlen($content) > $MAX_MESSAGE_CHARS * 2) {
            $content = mb_substr($content, 0, $MAX_MESSAGE_CHARS * 2);
        }
        $history[] = ['role' => $role, 'content' => $content];
    }
}

$ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : 'unknown';
$ipKey = preg_replace('/[^0-9a-fA-F.:]/', '', $ip);
if ($ipKey === '') {
    $ipKey = 'unknown';
}
if (!is_dir($RATE_LIMIT_DIR)) {
    @mkdir($RATE_LIMIT_DIR, 0750, true);
}
$rateFile = $RATE_LIMIT_DIR . '/' . hash('sha256', $ipKey) . '.txt';
$now = time();
if (is_file($rateFile)) {
    $last = (int) @file_get_contents($rateFile);
    if ($last > 0 && ($now - $last) < $RATE_LIMIT_SECONDS) {
        http_response_code(429);
        echo json_encode(['ok' => false, 'error' => 'rate']);
        exit;
    }
}
@file_put_contents($rateFile, (string) $now, LOCK_EX);

function ollama_request($url, $payload, $timeout) {
    $body = json_encode($payload);
    if ($body === false) {
        return [null, 'encode'];
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($errno !== 0 || $response === false) {
            return [null, 'curl'];
        }
        return [$response, $code];
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $body,
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    if ($response === false) {
        return [null, 'http'];
    }
    $code = 200;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }
    return [$response, $code];
}

function ollama_get($url, $timeout) {
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $response = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);
        if ($errno !== 0 || $response === false) {
            return null;
        }
        return $response;
    }
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $response = @file_get_contents($url, false, $ctx);
    return $response === false ? null : $response;
}

$model = $OLLAMA_MODEL;
if ($model === '') {
    $tagsRaw = ollama_get(rtrim($OLLAMA_URL, '/') . '/api/tags', 8);
    $tags = $tagsRaw ? json_decode($tagsRaw, true) : null;
    if (is_array($tags) && !empty($tags['models'][0]['name'])) {
        $model = (string) $tags['models'][0]['name'];
    }
}
if ($model === '') {
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'model']);
    exit;
}

$messages = array_merge(
    [
        [
            'role' => 'system',
            'content' => 'Du bist ein freundlicher Assistent auf der persönlichen Website gammel26.de. Antworte kurz, klar und auf Deutsch.',
        ],
    ],
    $history,
    [
        ['role' => 'user', 'content' => $message],
    ]
);

list($response, $status) = ollama_request(
    rtrim($OLLAMA_URL, '/') . '/api/chat',
    [
        'model' => $model,
        'messages' => $messages,
        'stream' => false,
    ],
    $REQUEST_TIMEOUT_SECONDS
);

if ($response === null) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'ollama', 'detail' => $status]);
    exit;
}

$parsed = json_decode($response, true);
if (!is_array($parsed) || !isset($parsed['message']['content'])) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'error' => 'reply', 'status' => $status]);
    exit;
}

$reply = trim((string) $parsed['message']['content']);
echo json_encode([
    'ok' => true,
    'reply' => $reply,
    'model' => $model,
]);
