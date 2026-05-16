<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_login();
$stmt = db()->prepare('SELECT * FROM webshop_orders WHERE uuid = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['uuid']]);
$orders = $stmt->fetchAll();

$itemStmt = db()->prepare('SELECT item_name, quantity, total_price FROM webshop_order_items WHERE order_id = ? ORDER BY id');
render_header('Orders');
?>
<section class="section-head"><div><p class="eyebrow">History</p><h1>Your Orders</h1></div></section>
<section class="orders-list">
    <?php foreach ($orders as $order): ?>
        <?php $itemStmt->execute([(int) $order['id']]); $items = $itemStmt->fetchAll(); ?>
        <article class="glass-card order-card">
            <div class="order-top"><h2>Order #<?= (int) $order['id'] ?></h2><span class="status"><?= e($order['status']) ?></span></div>
            <p><?= e((string) $order['created_at']) ?> · <?= e(format_shards((int) $order['total_price'])) ?></p>
            <ul>
                <?php foreach ($items as $item): ?>
                    <li><?= e($item['item_name']) ?> × <?= (int) $item['quantity'] ?> — <?= e(format_shards((int) $item['total_price'])) ?></li>
                <?php endforeach; ?>
            </ul>
        </article>
    <?php endforeach; ?>
    <?php if ($orders === []): ?><div class="glass-card center"><h2>No orders yet.</h2><p>Your purchases will appear here.</p></div><?php endif; ?>
</section>
<?php render_footer(); ?>
