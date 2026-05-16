<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/install.php';
require_once __DIR__ . '/../../app/functions.php';

$user = require_login_json();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    verify_csrf();
    $result = checkout_current_cart($user);
    json_response(['success' => true, 'message' => 'Checkout complete.', 'order_id' => $result['order_id'], 'total' => $result['total'], 'formatted_total' => format_shards((int) $result['total']), 'cart_count' => 0]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'error' => $exception->getMessage()], 400);
}
