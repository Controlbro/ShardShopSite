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
    $quantity = (int) ($_POST['quantity'] ?? 1);

    $stmt = db()->prepare('SELECT id FROM webshop_items WHERE id = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$itemId]);
    if (!$stmt->fetch()) {
        throw new RuntimeException('Item is not available.');
    }

    set_cart_quantity($itemId, $quantity);
    $details = get_cart_details();
    $lineTotal = 0;
    $savedQuantity = 0;
    foreach ($details['items'] as $item) {
        if ((int) $item['id'] === $itemId) {
            $lineTotal = (int) $item['line_total'];
            $savedQuantity = (int) $item['quantity'];
            break;
        }
    }

    json_response([
        'success' => true,
        'message' => $savedQuantity > 0 ? 'Cart quantity updated.' : 'Item removed from cart.',
        'cart_count' => cart_count(),
        'quantity' => $savedQuantity,
        'removed' => $savedQuantity === 0,
        'line_total' => $lineTotal,
        'formatted_line_total' => format_shards($lineTotal),
        'total' => (int) $details['total'],
        'formatted_total' => format_shards((int) $details['total']),
    ]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'error' => $exception->getMessage()], 400);
}
