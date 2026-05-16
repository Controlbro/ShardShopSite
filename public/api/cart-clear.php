<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/functions.php';

require_login_json();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    verify_csrf();
    clear_cart();
    json_response(['success' => true, 'message' => 'Cart cleared.', 'cart_count' => 0]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'error' => $exception->getMessage()], 400);
}
