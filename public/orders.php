<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_login();
$stmt = db()->prepare('SELECT * FROM webshop_orders WHERE uuid = ? ORDER BY created_at DESC LIMIT 50');
$stmt->execute([$user['uuid']]);
$orders = $stmt->fetchAll();

$itemStmt = db()->prepare('SELECT oi.item_name, oi.quantity, oi.unit_price, oi.total_price, wi.image_url, wi.category FROM webshop_order_items oi LEFT JOIN webshop_items wi ON wi.id = oi.item_id WHERE oi.order_id = ? ORDER BY oi.id');
render_header('Orders');
?>
<section class="section-head"><div><p class="eyebrow">History</p><h1>Your Orders</h1></div></section>
<section class="orders-list">
    <?php foreach ($orders as $order): ?>
        <?php $itemStmt->execute([(int) $order['id']]); $items = $itemStmt->fetchAll(); ?>
        <article class="glass-card order-card">
            <div class="order-top"><h2>Order #<?= (int) $order['id'] ?></h2><span class="status"><?= e($order['status']) ?></span></div>
            <p><?= e((string) $order['created_at']) ?> · <?= e(format_shards((int) $order['total_price'])) ?></p>
            <div class="order-item-list">
                <?php foreach ($items as $item): ?>
                    <div class="order-item-row">
                        <div class="mini-icon item-thumb">
                            <?php if (!empty($item['image_url'])): ?>
                                <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['item_name']) ?>">
                            <?php else: ?>
                                <span>✦</span>
                            <?php endif; ?>
                        </div>
                        <div class="order-item-main">
                            <strong><?= e($item['item_name']) ?></strong>
                            <span><?= e((string) ($item['category'] ?? 'Reward')) ?> · <?= e(format_shards((int) $item['unit_price'])) ?> each</span>
                        </div>
                        <span class="order-qty">× <?= (int) $item['quantity'] ?></span>
                        <strong><?= e(format_shards((int) $item['total_price'])) ?></strong>
                    </div>
                <?php endforeach; ?>
            </div>
        </article>
    <?php endforeach; ?>
    <?php if ($orders === []): ?><div class="glass-card center"><h2>No orders yet.</h2><p>Your purchases will appear here.</p></div><?php endif; ?>
</section>
<?php render_footer(); ?>
