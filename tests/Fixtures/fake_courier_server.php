<?php

/**
 * Local stand-in for the Pathao and Steadfast "create order" endpoints, run
 * with `php -S` by tests/Feature/CourierSendConcurrencyRaceTest.php — tests
 * never contact a real courier.
 *
 * Every create request is appended to FAKE_COURIER_LOG (one JSON line). The
 * response for an order reference (Steadfast `invoice` / Pathao
 * `merchant_order_id`) is taken from the front of its queue in
 * FAKE_COURIER_SCRIPT, e.g. {"ORD-1": [{"status": 422}, {"delay": 3}]};
 * an empty or missing queue means a successful booking after 2 seconds.
 * `GET /status_by_cid/{id}` always reports the consignment as existing.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isSteadfast = str_ends_with($path, '/create_order');
$isPathao = str_ends_with($path, '/aladdin/api/v1/orders');

header('Content-Type: application/json');

// Steadfast consignment lookup (used by manual reconciliation): every ID
// exists, answered after FAKE_COURIER_LOOKUP_DELAY seconds (default 1) so
// two concurrent reconciliations overlap.
if (preg_match('#/status_by_cid/([^/]+)$#', $path, $lookup)) {
    usleep((int) ((float) (getenv('FAKE_COURIER_LOOKUP_DELAY') ?: 1) * 1_000_000));
    echo json_encode(['status' => 200, 'delivery_status' => 'in_review']);

    return true;
}

if (! $isSteadfast && ! $isPathao) {
    http_response_code(404);
    echo '{}';

    return true;
}

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$reference = (string) ($body['invoice'] ?? $body['merchant_order_id'] ?? '');
$step = ['delay' => 2, 'status' => 200];

$script = fopen(getenv('FAKE_COURIER_SCRIPT'), 'c+');
flock($script, LOCK_EX);
$queues = json_decode(stream_get_contents($script), true) ?: [];
if (! empty($queues[$reference])) {
    $step = array_shift($queues[$reference]) + $step;
    ftruncate($script, 0);
    rewind($script);
    fwrite($script, json_encode($queues));
}
flock($script, LOCK_UN);
fclose($script);

$consignmentId = 'FAKE-'.bin2hex(random_bytes(4));
file_put_contents(getenv('FAKE_COURIER_LOG'), json_encode([
    'at' => microtime(true),
    'courier' => $isSteadfast ? 'steadfast' : 'pathao',
    'reference' => $reference,
    'status' => $step['status'],
    'consignment_id' => $step['status'] === 200 ? $consignmentId : null,
])."\n", FILE_APPEND | LOCK_EX);

usleep((int) ($step['delay'] * 1_000_000));

http_response_code($step['status']);

if ($step['status'] !== 200) {
    echo json_encode($isSteadfast ? ['status' => $step['status'], 'message' => 'Invalid recipient phone'] : ['message' => 'Invalid recipient phone']);

    return true;
}

echo $isSteadfast
    ? json_encode(['status' => 200, 'consignment' => ['consignment_id' => $consignmentId, 'tracking_code' => "TRK-{$consignmentId}", 'status' => 'in_review']])
    : json_encode(['type' => 'success', 'code' => 200, 'data' => ['consignment_id' => $consignmentId, 'merchant_order_id' => $reference, 'order_status' => 'Pending', 'delivery_fee' => 60]]);

return true;
