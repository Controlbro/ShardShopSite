<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/config.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}


function require_login_json(): array
{
    $user = current_user();
    if ($user === null) {
        json_response(['success' => false, 'error' => 'Login required.'], 401);
    }

    return $user;
}

function json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

function format_shards(int $amount): string
{
    $currencyName = CURRENCY_NAME;
    if ($amount === 1 && str_ends_with($currencyName, 's')) {
        $currencyName = substr($currencyName, 0, -1);
    }

    return number_format($amount) . ' ' . $currencyName;
}
function minecraft_head_url(string $uuid): string
{
    // Remove dashes from UUID
    $trimmed = str_replace('-', '', $uuid);

    return 'https://mc-heads.net/avatar/' . rawurlencode($trimmed) . '?size=64&overlay';
}

function cart(): array
{
    start_secure_session();
    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    return $_SESSION['cart'];
}

function cart_count(): int
{
    return array_sum(array_map('intval', cart()));
}

function set_cart(array $cart): void
{
    start_secure_session();
    $_SESSION['cart'] = $cart;
}

function add_to_cart(int $itemId, ?array $user = null): void
{
    $stmt = db()->prepare('SELECT id, name, one_time_purchase FROM webshop_items WHERE id = ? AND enabled = 1 LIMIT 1');
    $stmt->execute([$itemId]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new RuntimeException('Item is not available.');
    }

    $cart = cart();
    $key = (string) $itemId;
    $isOneTimePurchase = (int) ($item['one_time_purchase'] ?? 0) === 1;
    if ($isOneTimePurchase) {
        if ($user !== null) {
            $ownedStmt = db()->prepare('SELECT 1 FROM webshop_orders o INNER JOIN webshop_order_items oi ON oi.order_id = o.id WHERE o.uuid = ? AND oi.item_id = ? LIMIT 1');
            $ownedStmt->execute([(string) $user['uuid'], $itemId]);
            if ($ownedStmt->fetchColumn()) {
                throw new RuntimeException('You already purchased this one-time item.');
            }
        }
        $cart[$key] = 1;
    } else {
        $cart[$key] = min(99, ((int) ($cart[$key] ?? 0)) + 1);
    }

    set_cart($cart);
}


function set_cart_quantity(int $itemId, int $quantity): void
{
    $cart = cart();
    $key = (string) $itemId;
    if ($quantity <= 0) {
        unset($cart[$key]);
    } else {
        $cart[$key] = min(99, $quantity);
    }
    set_cart($cart);
}

function remove_from_cart(int $itemId): void
{
    $cart = cart();
    $key = (string) $itemId;
    if (isset($cart[$key])) {
        $cart[$key] = (int) $cart[$key] - 1;
        if ($cart[$key] <= 0) {
            unset($cart[$key]);
        }
    }
    set_cart($cart);
}

function clear_cart(): void
{
    set_cart([]);
}

function get_cart_details(): array
{
    $cart = cart();
    if ($cart === []) {
        return ['items' => [], 'total' => 0];
    }

    $ids = array_values(array_filter(array_map('intval', array_keys($cart)), fn($id) => $id > 0));
    if ($ids === []) {
        clear_cart();
        return ['items' => [], 'total' => 0];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare("SELECT * FROM webshop_items WHERE enabled = 1 AND id IN ($placeholders)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    $items = [];
    $total = 0;
    foreach ($rows as $row) {
        $id = (int) $row['id'];
        $isOneTimePurchase = (int) ($row['one_time_purchase'] ?? 0) === 1;
        $maxQuantity = $isOneTimePurchase ? 1 : 99;
        $quantity = max(1, min($maxQuantity, (int) ($cart[(string) $id] ?? 0)));
        $lineTotal = (int) $row['price'] * $quantity;
        $row['quantity'] = $quantity;
        $row['line_total'] = $lineTotal;
        $items[] = $row;
        $total += $lineTotal;
    }

    return ['items' => $items, 'total' => $total];
}

function audit_log(?string $uuid, ?string $username, string $action, ?string $message = null): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $stmt = db()->prepare('INSERT INTO webshop_audit_log (uuid, username, action, message, ip_address) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$uuid, $username, $action, $message, $ip]);
}


function can_edit_shop_items(?array $user = null): bool
{
    $user ??= current_user();
    if ($user === null) {
        return false;
    }

    $editorUuid = strtolower(trim((string) SHOP_EDITOR_UUID));
    if ($editorUuid === '' || $editorUuid === '00000000-0000-0000-0000-000000000000') {
        return false;
    }

    return hash_equals($editorUuid, strtolower((string) ($user['uuid'] ?? '')));
}

function require_shop_editor(): array
{
    $user = require_login();
    if (!can_edit_shop_items($user)) {
        http_response_code(403);
        render_header('Forbidden');
        ?>
        <div class="glass-card center">
            <h1>Shop editor locked</h1>
            <p>Only the configured editor UUID can edit shop items.</p>
            <a class="btn" href="/shop.php">Back to Shop</a>
        </div>
        <?php
        render_footer();
        exit;
    }

    return $user;
}

function render_header(string $title): void
{
    $user = current_user();
    $count = cart_count();
    $csrf = csrf_token();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e($csrf) ?>">
    <title><?= e($title) ?> - <?= e(SITE_NAME) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
<div class="page-shell">
    <nav class="navbar">
        <a class="brand" href="/shop.php"><span class="brand-gem">✦</span><?= e(SITE_NAME) ?></a>
        <?php if ($user): ?>
            <div class="nav-user">
                <img class="avatar" src="<?= e(minecraft_head_url($user['uuid'])) ?>" alt="<?= e($user['username']) ?> head">
                <span class="username"><?= e($user['username']) ?></span>
                <span class="balance-pill" data-balance>Loading...</span>
                <a class="nav-link" href="/orders.php">Orders</a>
                <?php if (can_edit_shop_items($user)): ?>
                    <a class="nav-link" href="/admin-items.php">Edit Shop</a>
                <?php endif; ?>
                <a class="cart-link" href="/cart.php">Cart <span class="cart-badge" data-cart-count><?= $count ?></span></a>
                <a class="nav-link" href="/logout.php">Logout</a>
            </div>
        <?php else: ?>
            <div class="nav-user"><a class="btn btn-small" href="/login.php">Login</a></div>
        <?php endif; ?>
    </nav>
    <main>
    <?php
}

function render_footer(): void
{
    ?>
    </main>
</div>
<script src="/assets/app.js"></script>
</body>
</html>
    <?php
}

function checkout_current_cart(array $user): array
{
    require_once __DIR__ . '/balance.php';

    $pdo = db();
    $details = get_cart_details();
    if ($details['items'] === []) {
        throw new RuntimeException('Your cart is empty.');
    }

    $pdo->beginTransaction();
    try {
        $details = get_cart_details();
        if ($details['items'] === []) {
            throw new RuntimeException('Your cart is empty.');
        }

        $total = (int) $details['total'];
        $balance = lock_balance_for_update($pdo, $user['uuid'], $user['username']);
        if ($balance < $total) {
            throw new RuntimeException('You do not have enough ' . CURRENCY_NAME . ' for this purchase.');
        }

        subtract_locked_balance($pdo, $user['uuid'], $user['username'], $total);

        $stmt = $pdo->prepare('INSERT INTO webshop_orders (uuid, username, total_price, status) VALUES (?, ?, ?, ?)');
        $stmt->execute([$user['uuid'], $user['username'], $total, 'completed']);
        $orderId = (int) $pdo->lastInsertId();

        $orderItemStmt = $pdo->prepare('INSERT INTO webshop_order_items (order_id, item_id, item_name, quantity, unit_price, total_price, commands) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $commandStmt = $pdo->prepare("INSERT INTO shop_pending_commands (order_id, uuid, username, command, signature, status) VALUES (?, ?, ?, ?, ?, 'pending')");

        foreach ($details['items'] as $item) {
            if ((int) ($item['one_time_purchase'] ?? 0) === 1) {
                if ((int) $item['quantity'] > 1) {
                    throw new RuntimeException('One-time purchase items can only be bought once per order.');
                }
                $ownedStmt = $pdo->prepare('SELECT 1 FROM webshop_orders o INNER JOIN webshop_order_items oi ON oi.order_id = o.id WHERE o.uuid = ? AND oi.item_id = ? LIMIT 1');
                $ownedStmt->execute([(string) $user['uuid'], (int) $item['id']]);
                if ($ownedStmt->fetchColumn()) {
                    throw new RuntimeException('You already purchased one of the selected one-time items.');
                }
            }
            $commandsJson = (string) $item['commands'];
            $commands = json_decode($commandsJson, true);
            if (!is_array($commands)) {
                throw new RuntimeException('Shop item commands are invalid for item: ' . $item['name']);
            }

            $lineTotal = (int) $item['line_total'];
            $orderItemStmt->execute([$orderId, (int) $item['id'], (string) $item['name'], (int) $item['quantity'], (int) $item['price'], $lineTotal, $commandsJson]);

            for ($i = 0; $i < (int) $item['quantity']; $i++) {
                foreach ($commands as $command) {
                    $command = (string) $command;
                    $message = $orderId . '|' . $user['uuid'] . '|' . $user['username'] . '|' . $command;
                    $signature = hash_hmac('sha256', $message, WEBSHOP_API_KEY);
                    $commandStmt->execute([$orderId, $user['uuid'], $user['username'], $command, $signature]);
                }
            }
        }

        audit_log($user['uuid'], $user['username'], 'checkout_completed', 'Order #' . $orderId . ' completed for ' . $total . ' shards.');
        $pdo->commit();
        clear_cart();

        return ['order_id' => $orderId, 'total' => $total];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        audit_log($user['uuid'], $user['username'], 'checkout_failed', $exception->getMessage());
        throw $exception;
    }
}
