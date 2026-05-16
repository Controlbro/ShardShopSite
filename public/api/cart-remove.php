<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/functions.php';

require_login_json();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    remove_from_cart($itemId);
    json_response(['success' => true, 'message' => 'Item removed from cart.', 'cart_count' => cart_count()]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'error' => $exception->getMessage()], 400);
}
