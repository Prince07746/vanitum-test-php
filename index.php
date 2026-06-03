<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

$path  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$parts = array_values(array_filter(explode('/', trim($path, '/'))));

function out(mixed $data, int $code = 200): never {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function call_service(string $slug): mixed {
    $key = strtoupper(str_replace('-', '_', $slug)) . '_INTERNAL_URL';
    $url = getenv($key);
    if (!$url) {
        out(['error' => "$key not set"], 400);
    }
    $resp = @file_get_contents(rtrim($url, '/') . '/health');
    if ($resp === false) {
        out(['error' => "Failed to reach $slug"], 502);
    }
    return json_decode($resp, true);
}

$route = $parts[0] ?? '';

if ($route === 'health') {
    out(['status' => 'ok', 'service' => 'vanitum-test-php', 'time' => date('c')]);
}

if ($route === 'env') {
    $urls = array_filter(
        getenv(),
        fn(string $k) => str_ends_with($k, '_INTERNAL_URL'),
        ARRAY_FILTER_USE_KEY
    );
    out(['service' => 'vanitum-test-php', 'internal_urls' => $urls]);
}

if ($route === 'call' && isset($parts[1])) {
    $slug   = $parts[1];
    $result = call_service($slug);
    out(['caller' => 'vanitum-test-php', 'callee' => $slug, 'response' => $result]);
}

if ($route === 'chain' && isset($parts[1], $parts[2])) {
    $slug1 = $parts[1];
    $slug2 = $parts[2];
    $key   = strtoupper(str_replace('-', '_', $slug1)) . '_INTERNAL_URL';
    $url   = getenv($key);
    if (!$url) out(['error' => "$key not set"], 400);
    $resp = @file_get_contents(rtrim($url, '/') . '/call/' . $slug2);
    if ($resp === false) out(['error' => "Failed to reach $slug1"], 502);
    out([
        'caller'   => 'vanitum-test-php',
        'hop1'     => $slug1,
        'hop2'     => $slug2,
        'response' => json_decode($resp, true),
    ]);
}

out(['error' => 'not found'], 404);
