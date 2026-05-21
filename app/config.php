<?php

declare(strict_types=1);

// Database connection. Replace DB_PASS with your real MySQL password before deploying.
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'besteconomy');
define('DB_USER', 'besteconomy');
define('DB_PASS', 'Quentinm00!');

// Must match the Minecraft plugin webshop.api-key. Never expose this in public pages or logs.
define('WEBSHOP_API_KEY', 'Y7mQ2vK9xLp4Nz8RjT6cWd1HsF3uBaXe');

define('SITE_NAME', 'ShardShop');
define('CURRENCY_NAME', 'Shards');
define('SHOP_CURRENCY_SYMBOL', '✦');

// Balance source: "plugin" uses your economy plugin table; "webshop_fallback" uses webshop_balances.
define('BALANCE_SOURCE', 'plugin');

// Economy plugin balance table settings. Change these if your plugin uses different names.
define('BALANCE_TABLE', 'player_balances');
define('BALANCE_UUID_COLUMN', 'uuid');
define('BALANCE_CURRENCY_COLUMN', 'currency');
define('BALANCE_AMOUNT_COLUMN', 'amount');
define('BALANCE_CURRENCY_VALUE', 'Shards');

// Session hardening.
define('SESSION_NAME', 'shardshop_session');
define('REMEMBER_COOKIE_NAME', 'shardshop_remember');
define('REMEMBER_COOKIE_LIFETIME', 60 * 60 * 24 * 30);

// UUID allowed to access the admin item editor.
define('SHOP_EDITOR_UUID', '00000000-0000-0000-0000-000000000000');
