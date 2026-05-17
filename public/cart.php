<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_login();
$details = get_cart_details();
render_header('Cart');
?>
<section class="section-head">
    <div><p class="eyebrow">Review</p><h1>Your Cart</h1></div>
    <a class="btn btn-secondary" href="/shop.php">Continue Shopping</a>
</section>
<?php if ($details['items'] === []): ?>
    <div class="glass-card center"><h2>Your cart is empty.</h2><p>Add rewards from the shop to begin checkout.</p><a class="btn" href="/shop.php">Browse Shop</a></div>
<?php else: ?>
    <section class="cart-layout">
        <div class="cart-list">
            <?php foreach ($details['items'] as $item): ?>
                <article class="cart-row" data-cart-row data-item-id="<?= (int) $item['id'] ?>">
                    <div class="mini-icon item-thumb">
                        <?php if (!empty($item['image_url'])): ?>
                            <img src="<?= e($item['image_url']) ?>" alt="<?= e($item['name']) ?>">
                        <?php else: ?>
                            <span>✦</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2><?= e($item['name']) ?></h2>
                        <p><?= e($item['category']) ?> · <?= e(format_shards((int) $item['price'])) ?> each</p>
                    </div>
                    <form class="cart-quantity-form" action="/api/cart-update.php" method="post" data-cart-quantity-form>
                        <?= csrf_field() ?>
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                        <label class="quantity-label">Qty
                            <input class="quantity-input" type="number" name="quantity" min="0" max="99" value="<?= (int) $item['quantity'] ?>" inputmode="numeric" aria-label="Quantity for <?= e($item['name']) ?>">
                        </label>
                    </form>
                    <strong data-line-total><?= e(format_shards((int) $item['line_total'])) ?></strong>
                    <form class="ajax-form" action="/api/cart-remove.php" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                        <button class="btn btn-danger btn-small" type="submit">Remove</button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
        <aside class="summary-card">
            <h2>Order Summary</h2>
            <div class="summary-line"><span>Total</span><strong data-cart-total><?= e(format_shards((int) $details['total'])) ?></strong></div>
            <form class="checkout-form" action="/api/checkout.php" method="post">
                <?= csrf_field() ?>
                <button class="btn full" type="submit" data-checkout-button>Checkout</button>
            </form>
            <form class="ajax-form" action="/api/cart-clear.php" method="post">
                <?= csrf_field() ?>
                <button class="btn btn-secondary full" type="submit">Clear Cart</button>
            </form>
            <div data-checkout-result></div>
        </aside>
    </section>
<?php endif; ?>
<?php render_footer(); ?>
