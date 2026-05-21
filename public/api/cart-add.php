<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/functions.php';

$user = require_login_json();
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('POST required.');
    }
    verify_csrf();
    $itemId = (int) ($_POST['item_id'] ?? 0);
    $stmt = db()->prepare('SELECT id FROM webshop_items WHERE id = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$itemId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Item is not available.');
    }
    add_to_cart($itemId, $user);
    json_response(['success' => true, 'message' => 'Item added to cart.', 'cart_count' => cart_count()]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'error' => $exception->getMessage()], 400);
}
