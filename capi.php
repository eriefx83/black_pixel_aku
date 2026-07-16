<?php
/**
 * GoldTrap Meta Conversions API endpoint
 * Upload this file to the SAME folder as index.html on cPanel.
 * IMPORTANT: Replace YOUR_META_ACCESS_TOKEN_HERE with your Meta Pixel access token.
 */

header('Content-Type: application/json');

// ====== CONFIG ======
$pixel_id = '1304265291373359';
$access_token = 'EAAN5JgZAZCimwBRtUr3NOyA8ZCZCd3zN5JvWcQTKmN8ECCZChVw3ZCHf2ZAvU3UfZAIYStxZBhXmWZAGsVO32Ag6ZBNxF78vFSXJjI21xti6ZCZCfO0bTg2bWSCHHtsR7DUqW9t2LKUJz9CYxXz9qLwzXeqttbOpZC36uVj0EEptCxHNr4nnxOpYMFm2AOWVGomvx1rwZDZD';

// Optional for Events Manager > Test Events only.
// Example: $test_event_code = 'TEST81654';
// Leave empty for live ads.
$test_event_code = 'TEST13884';

// Use a recent Graph API version supported by Meta.
$graph_version = 'v22.0';
// ====================

if ($access_token === 'YOUR_META_ACCESS_TOKEN_HERE' || trim($access_token) === '') {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Missing Meta access token. Edit capi.php and replace YOUR_META_ACCESS_TOKEN_HERE.'
    ]);
    exit;
}

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);

if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON payload.']);
    exit;
}

$allowed_events = ['PageView', 'Contact', 'Lead'];
$event_name = isset($input['event_name']) ? preg_replace('/[^A-Za-z0-9_]/', '', $input['event_name']) : '';

if (!in_array($event_name, $allowed_events, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unsupported event name.']);
    exit;
}

$event_id = isset($input['event_id']) ? substr(preg_replace('/[^A-Za-z0-9_\-\.]/', '', $input['event_id']), 0, 120) : uniqid($event_name . '_', true);

$event_source_url = isset($input['event_source_url']) ? filter_var($input['event_source_url'], FILTER_SANITIZE_URL) : '';
if (!$event_source_url) {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    $event_source_url = $scheme . '://' . $host . $uri;
}

$client_ip = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '';

// If X-Forwarded-For contains multiple IPs, use the first one.
if (strpos($client_ip, ',') !== false) {
    $client_ip = trim(explode(',', $client_ip)[0]);
}

$client_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$user_data = [
    'client_ip_address' => $client_ip,
    'client_user_agent' => $client_user_agent
];

if (!empty($input['fbp']) && is_string($input['fbp'])) {
    $user_data['fbp'] = substr($input['fbp'], 0, 200);
}

if (!empty($input['fbc']) && is_string($input['fbc'])) {
    $user_data['fbc'] = substr($input['fbc'], 0, 200);
}

$event = [
    'event_name' => $event_name,
    'event_time' => time(),
    'event_id' => $event_id,
    'action_source' => 'website',
    'event_source_url' => $event_source_url,
    'user_data' => $user_data
];

$payload = ['data' => [$event]];

if (!empty($test_event_code)) {
    $payload['test_event_code'] = $test_event_code;
}

$url = 'https://graph.facebook.com/' . $graph_version . '/' . $pixel_id . '/events?access_token=' . urlencode($access_token);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if ($response === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $curl_error]);
    exit;
}

http_response_code($http_code >= 200 && $http_code < 300 ? 200 : 500);
echo json_encode([
    'success' => $http_code >= 200 && $http_code < 300,
    'http_code' => $http_code,
    'meta_response' => json_decode($response, true)
]);
