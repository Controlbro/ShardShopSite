<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';

if (is_logged_in()) {
    redirect('/shop.php');
}

$previewItems = db()->query('SELECT name, description, image_url, category, price, one_time_purchase FROM webshop_items WHERE enabled = 1 ORDER BY sort_order, category, name LIMIT 6')->fetchAll();

render_header('Welcome');
?>
<section class="hero">
    <div class="hero-card">
        <p class="eyebrow">Minecraft Shards Webshop</p>
        <h1>Spend your hard-earned Shards on rewards.</h1>
        <p class="hero-text">Login with the username and password you created in game with <strong>/shop</strong>, browse rewards, and receive purchases automatically!</p>
        <div class="hero-actions">
            <a class="btn" href="/login.php">Login to Shop</a>
        </div>
    </div>
</section>

<section class="section-head">
    <div><p class="eyebrow">Preview</p><h2>Popular Rewards</h2></div>
</section>
<section class="shop-grid">
<?php foreach ($previewItems as $item): ?>
    <article class="item-card">
        <div class="item-image">
            <?php if (!empty($item['image_url'])): ?><img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>"><?php else: ?><span>✦</span><?php endif; ?>
        </div>
        <div class="item-content">
            <span class="category"><?= e($item['category']) ?></span>
            <h2><?= e($item['name']) ?></h2>
            <?php if ((int) ($item['one_time_purchase'] ?? 0) === 1): ?><span class="one-time-chip">One-time purchase</span><?php endif; ?>
            <p><?= e($item['description'] ?? 'Server reward delivered after checkout.') ?></p>
            <div class="item-bottom"><strong><?= e(format_shards((int) $item['price'])) ?></strong></div>
        </div>
    </article>
<?php endforeach; ?>
</section>
<?php render_footer(); ?>
