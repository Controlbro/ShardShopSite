# ShardShop PHP/MySQL Webshop

A complete plain PHP 8.3 Minecraft Shards webshop. It logs players in from the plugin-managed `shop_accounts` table, reads a configurable Shards balance, queues HMAC-signed reward commands in `shop_pending_commands`, and never executes commands on the web server.

## Deploy

Copy this repository to `/var/www/shardshop`, then edit `app/config.php`:

- Set `DB_PASS` to your real MySQL password.
- Set `WEBSHOP_API_KEY` to the same value as your Minecraft plugin `webshop.api-key`.
- If your economy table is not `balances`, update `BALANCE_TABLE`, `BALANCE_UUID_COLUMN`, `BALANCE_CURRENCY_COLUMN`, `BALANCE_AMOUNT_COLUMN`, and `BALANCE_CURRENCY_VALUE`.
- If you want the standalone fallback balance table instead, set `BALANCE_SOURCE` to `webshop_fallback`.

The installer is safe to run multiple times and automatically creates missing website tables plus seed items. It runs when `public/shop.php`, `public/login.php`, or checkout endpoints load.

## Nginx config

```nginx
server {
    listen 80;
    listen [::]:80;

    server_name shop.YOURDOMAIN.com;

    root /var/www/shardshop/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }

    location ~ /\. {
        deny all;
    }
}
```

## Ubuntu commands

```bash
sudo chown -R www-data:www-data /var/www/shardshop
sudo chmod -R 755 /var/www/shardshop
sudo nginx -t
sudo systemctl reload nginx
```

## Security notes

- Prepared statements are used for user-controlled values.
- POST endpoints require PHP sessions and CSRF tokens.
- Passwords are verified with `password_verify()` against `shop_accounts.password_hash`.
- Checkout uses a MySQL transaction and `SELECT ... FOR UPDATE` balance locking.
- The website stores command placeholders unchanged and signs each pending command with HMAC-SHA256.
- The raw webshop API key is never rendered to public pages and is not logged.
