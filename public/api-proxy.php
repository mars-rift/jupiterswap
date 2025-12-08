<?php
/**
 * Simple PHP proxy for selected Jupiter API endpoints
 * Usage examples:
 * GET /api-proxy.php?target=ultra&path=order&query=...   -> https://ultra-api.jup.ag/order?query=...
 * GET /api-proxy.php?target=lite&path=program-id-to-label  -> https://lite-api.jup.ag/swap/v1/program-id-to-label
 * POST /api-proxy.php?target=ultra&path=execute (POST body forwarded)
 *
 * Security: This proxy restricts forwarding to known allowed targets and paths only.
 * Optional server-side API key can be provided through environment variable PROXY_API_KEY. (ULTRA API keys should not be passed from the client.)
 */

// Allowed base map
$allowed = [
    'ultra' => 'https://ultra-api.jup.ag',
    'lite' => 'https://lite-api.jup.ag/swap/v1',
    'data' => 'https://datapi.jup.ag/v1'
];

// Accept only certain hosts
$target = isset($_GET['target']) ? $_GET['target'] : null;
if (!$target || !isset($allowed[$target])) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid target']);
    exit;
}

$base = $allowed[$target];
// Path for upstream; relative path only
$path = isset($_GET['path']) ? ltrim($_GET['path'], '/') : '';

// HTTP method and CORS preflight handling (we need the method early)
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-Client-Platform');
    http_response_code(204);
    exit;
}

if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH'])) {
    http_response_code(405);
    header('Allow: GET, POST, PUT, PATCH, OPTIONS');
    exit;
}

// Validate path: allow only a limited set of characters and prevent traversal
if (strlen($path) == 0 || strlen($path) > 256 || preg_match('/\.\.|[^a-zA-Z0-9_\/-]/', $path)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'invalid path']);
    exit;
}

// Minimal whitelist for allowed endpoints per target
$allowMap = [
    'ultra' => [
        // endpoints allowed on the ultra API
        'execute' => ['POST'],
        'order' => ['GET'],
        'order/routers' => ['GET'],
        'balances' => ['GET'], // allows balances/<address>
        'shield' => ['GET'],
    ],
    'lite' => [
        'program-id-to-label' => ['GET'],
    ],
    'data' => [
        // allow read-only data endpoints if necessary
    ],
];

// Check path permission
// $method already set earlier
$isAllowed = false;
foreach ($allowMap[$target] as $allowedPath => $methods) {
    // direct match
    if ($path === $allowedPath) {
        $isAllowed = in_array($method, $methods);
        break;
    }
    // prefix match (e.g., balances/ADDRESS)
    if (substr($path, 0, strlen($allowedPath) + 1) === $allowedPath . '/') {
        $isAllowed = in_array($method, $methods);
        break;
    }
    // direct match
}

if (!$isAllowed) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'forbidden path or method']);
    exit;
}

// Build URL
$query = isset($_GET['query']) ? $_GET['query'] : $_SERVER['QUERY_STRING'];
// Remove our internal params (target, path)
parse_str($query, $qparts);
unset($qparts['target']); unset($qparts['path']); unset($qparts['query']);
$qs = http_build_query($qparts);
$url = rtrim($base, '/') . '/' . $path . ($qs ? "?$qs" : '');

// Prepare curl
$ch = curl_init($url);

// Forward method and body
// HTTP method and CORS preflight handling
$method = $_SERVER['REQUEST_METHOD'];
if ($method === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-Client-Platform');
    http_response_code(204);
    exit;
}

if (!in_array($method, ['GET', 'POST', 'PUT', 'PATCH'])) {
    http_response_code(405);
    header('Allow: GET, POST, PUT, PATCH, OPTIONS');
    exit;
}
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

// Forward headers we permit
$requestHeaders = [];
foreach (getallheaders() as $name => $value) {
    $n = strtolower($name);
    // Skip any potentially sensitive headers
    if (in_array($n, ['host', 'authorization', 'cookie'])) continue;
    // Allow only certain headers
    if (in_array($n, ['content-type', 'accept', 'x-requested-with', 'x-client-platform'])) {
        $requestHeaders[] = $name . ': ' . $value;
    }
}

// Optionally add server-side secrets
$serverApiKey = getenv('PROXY_API_KEY');
if ($serverApiKey) {
    $requestHeaders[] = 'Authorization: Bearer ' . $serverApiKey;
}

curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);

if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
    $contentLength = isset($_SERVER['CONTENT_LENGTH']) ? (int)$_SERVER['CONTENT_LENGTH'] : 0;
    if ($contentLength > 1024 * 1024) {
        http_response_code(413);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'request entity too large']);
        exit;
    }
    $input = file_get_contents('php://input');
    curl_setopt($ch, CURLOPT_POSTFIELDS, $input);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
}

// Untested: timeouts & TLS
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$errno = curl_errno($ch);
if ($errno) {
    http_response_code(502);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'upstream request failed', 'detail' => curl_error($ch)]);
    curl_close($ch);
    exit;
}

$statusCode = $info['http_code'] ?? 200;
// Response headers: a minimal set
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept, X-Requested-With, X-Client-Platform');
// Proxy through content type detected in server response if available
$contentType = 'application/json';
if (isset($info['content_type'])) {
    $contentType = $info['content_type'];
}
header('Content-Type: ' . $contentType);
http_response_code($statusCode);
echo $response;
curl_close($ch);
exit;

?>
