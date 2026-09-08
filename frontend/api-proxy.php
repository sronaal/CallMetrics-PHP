<?php
/**
 * API Reverse Proxy
 * Reenvía requests /api-proxy/* → http://localhost:8080/api/*
 * Resuelve mixed content (HTTPS frontend → HTTP backend).
 */
declare(strict_types=1);

$BACKEND = 'http://localhost:8080';

// Extract the path after api-proxy.php/
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$prefix = '/api-proxy.php/';
$pos = strpos($uri, $prefix);
if ($pos === false) {
    http_response_code(404);
    exit('Not found');
}
$backendPath = substr($uri, $pos + strlen($prefix));
$backendUrl  = $BACKEND . '/api/' . $backendPath;

// Build curl options
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $backendUrl,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CUSTOMREQUEST  => $_SERVER['REQUEST_METHOD'] ?? 'GET',
]);

// Forward headers (Authorization, Content-Type, etc.)
$headers = [];
foreach (getallheaders() as $k => $v) {
    if (in_array(strtolower($k), ['host', 'connection'])) continue;
    $headers[] = "$k: $v";
}
if ($headers) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
}

// Forward body for POST/PUT/PATCH
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $body = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
}

$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(502);
    echo json_encode(['error' => 'Backend unreachable', 'detail' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
curl_close($ch);

// Split response into headers + body
$responseHeaders = substr($response, 0, $headerSize);
$responseBody    = substr($response, $headerSize);

// Forward response headers
foreach (explode("\r\n", trim($responseHeaders)) as $line) {
    // Skip status line and hop-by-hop headers
    if (stripos($line, 'HTTP/') === 0) continue;
    if (preg_match('/^(transfer-encoding|connection|content-encoding):/i', $line)) continue;
    header($line, false);
}

http_response_code($statusCode);
echo $responseBody;
