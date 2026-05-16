<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

$user = require_login();
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $result = checkout_current_cart($user);
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

render_header('Checkout');
?>
<section class="auth-wrap">
    <div class="auth-card">
        <p class="eyebrow">Checkout</p>
        <?php if ($result): ?>
            <div class="alert alert-success">Order #<?= (int) $result['order_id'] ?> completed for <?= e(format_shards((int) $result['total'])) ?>.</div>
            <p>Your signed reward commands are now pending for the Minecraft plugin.</p>
            <a class="btn full" href="/orders.php">View Orders</a>
        <?php elseif ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
            <a class="btn full" href="/cart.php">Back to Cart</a>
        <?php else: ?>
            <h1>Ready to checkout?</h1>
            <p>Confirm your cart to spend <?= e(CURRENCY_NAME) ?> and queue your rewards.</p>
            <form method="post" action="/checkout.php">
                <?= csrf_field() ?>
                <button class="btn full" type="submit" data-checkout-button>Confirm Checkout</button>
            </form>
        <?php endif; ?>
    </div>
</section>
<?php render_footer(); ?>
