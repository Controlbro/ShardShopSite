<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_login();
$category = trim((string) ($_GET['category'] ?? ''));

$categories = db()->query("SELECT DISTINCT category FROM webshop_items WHERE enabled = 1 ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
if ($category !== '') {
    $stmt = db()->prepare('SELECT * FROM webshop_items WHERE enabled = 1 AND category = ? ORDER BY sort_order, name');
    $stmt->execute([$category]);
} else {
    $stmt = db()->query('SELECT * FROM webshop_items WHERE enabled = 1 ORDER BY sort_order, category, name');
}
$items = $stmt->fetchAll();

$recentStmt = db()->query("
    SELECT oi.item_name, o.username, o.created_at, wi.image_url, wi.category
    FROM webshop_order_items oi
    INNER JOIN webshop_orders o ON o.id = oi.order_id
    LEFT JOIN webshop_items wi ON wi.id = oi.item_id
    ORDER BY o.created_at DESC, oi.id DESC
    LIMIT 8
");
$recentPurchases = $recentStmt->fetchAll();

render_header('Shop');
?>
<section class="section-head">
    <div>
        <p class="eyebrow">Spend <?= e(CURRENCY_NAME) ?></p>
        <h1>Shop Rewards</h1>
        <p>Commands are queued for the Minecraft plugin with placeholders intact.</p>
    </div>
</section>
<div class="filters">
    <a class="chip <?= $category === '' ? 'active' : '' ?>" href="/shop.php">All</a>
    <?php foreach ($categories as $cat): ?>
        <a class="chip <?= $category === $cat ? 'active' : '' ?>" href="/shop.php?category=<?= urlencode((string) $cat) ?>"><?= e((string) $cat) ?></a>
    <?php endforeach; ?>
</div>
<div class="shop-layout">
    <section class="shop-grid">
        <?php foreach ($items as $item): ?>
            <article class="item-card">
                <div class="item-image">
                    <?php if (!empty($item['image_url'])): ?>
                        <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>">
                    <?php else: ?>
                        <span>✦</span>
                    <?php endif; ?>
                </div>
                <div class="item-content">
                    <span class="category"><?= e($item['category']) ?></span>
                    <h2><?= e($item['name']) ?></h2>
                    <?php if ((int) ($item['one_time_purchase'] ?? 0) === 1): ?><span class="one-time-chip">One-time purchase</span><?php endif; ?>
                    <p><?= e($item['description'] ?? 'A server reward delivered by command queue.') ?></p>
                    <div class="item-bottom">
                        <strong><?= e(format_shards((int) $item['price'])) ?></strong>
                        <form class="ajax-form" action="/api/cart-add.php" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                            <button class="btn btn-small" type="submit">Add to Cart</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        <?php if ($items === []): ?><div class="alert">No enabled items found in this category.</div><?php endif; ?>
    </section>
    <aside class="recent-purchases" aria-labelledby="recent-purchases-title">
        <div class="sidebar-title">
            <p class="eyebrow">Live Feed</p>
            <h2 id="recent-purchases-title">Recent Purchases</h2>
        </div>
        <div class="recent-list">
            <?php foreach ($recentPurchases as $purchase): ?>
                <article class="recent-purchase">
                    <div class="mini-icon item-thumb">
                        <?php if (!empty($purchase['image_url'])): ?>
                            <img src="<?= e($purchase['image_url']) ?>" alt="<?= e($purchase['item_name']) ?>">
                        <?php else: ?>
                            <span>✦</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong><?= e($purchase['item_name']) ?></strong>
                        <p><?= e($purchase['username']) ?> purchased this reward</p>
                    </div>
                </article>
            <?php endforeach; ?>
            <?php if ($recentPurchases === []): ?>
                <div class="empty-feed">
                    <strong>No purchases yet</strong>
                    <p>Fresh orders will show up here.</p>
                </div>
            <?php endif; ?>
        </div>
    </aside>
</div>
<?php render_footer(); ?>
