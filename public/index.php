<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';

if (is_logged_in()) {
    redirect('/shop.php');
}

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
<?php render_footer(); ?>
