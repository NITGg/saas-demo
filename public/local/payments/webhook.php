<?php
define('NO_MOODLE_COOKIES', true);
define('AJAX_SCRIPT', true);

require_once(__DIR__ . '/../../config.php');

$provider_name = required_param('provider', PARAM_ALPHANUMEXT);

$payload = file_get_contents('php://input');
if (empty($payload)) {
    http_response_code(400);
    echo json_encode(['error' => 'Empty payload']);
    exit;
}

$headers = [];
foreach ($_SERVER as $key => $value) {
    if (strpos($key, 'HTTP_') === 0) {
        $header_name = str_replace('_', '-', substr($key, 5));
        $headers[$header_name] = $value;
    }
}
// Also check lowercase versions commonly used.
$headers['x-kashier-signature'] = $headers['X-KASHIER-SIGNATURE'] ?? $headers['x-kashier-signature'] ?? '';

try {
    $success = \local_payments\manager::process_webhook($provider_name, $payload, $headers);

    if ($success) {
        http_response_code(200);
        echo json_encode(['status' => 'ok']);
    } else {
        http_response_code(400);
        echo json_encode(['status' => 'failed']);
    }
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
}
