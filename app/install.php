<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

function run_install(): void
{
    $pdo = db();

    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_accounts (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL UNIQUE,
        username VARCHAR(16) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS shop_pending_commands (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT NULL,
        uuid VARCHAR(36) NOT NULL,
        username VARCHAR(16) NOT NULL,
        command TEXT NOT NULL,
        signature VARCHAR(128) NOT NULL,
        status ENUM('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
        attempts INT NOT NULL DEFAULT 0,
        last_error TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        processed_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webshop_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT NULL,
        image_url VARCHAR(255) NULL,
        price BIGINT NOT NULL,
        category VARCHAR(64) NOT NULL DEFAULT 'General',
        commands JSON NOT NULL,
        enabled TINYINT(1) NOT NULL DEFAULT 1,
        sort_order INT NOT NULL DEFAULT 0,
        one_time_purchase TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webshop_orders (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NOT NULL,
        username VARCHAR(16) NOT NULL,
        total_price BIGINT NOT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'completed',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_uuid_created (uuid, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webshop_order_items (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        order_id BIGINT NOT NULL,
        item_id BIGINT NOT NULL,
        item_name VARCHAR(100) NOT NULL,
        quantity INT NOT NULL,
        unit_price BIGINT NOT NULL,
        total_price BIGINT NOT NULL,
        commands JSON NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webshop_audit_log (
        id BIGINT AUTO_INCREMENT PRIMARY KEY,
        uuid VARCHAR(36) NULL,
        username VARCHAR(16) NULL,
        action VARCHAR(64) NOT NULL,
        message TEXT NULL,
        ip_address VARCHAR(64) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_audit_user (uuid, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS webshop_balances (
        uuid VARCHAR(36) PRIMARY KEY,
        username VARCHAR(16) NOT NULL,
        shards BIGINT NOT NULL DEFAULT 0,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $hasOneTimePurchase = (int) $pdo->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'webshop_items' AND COLUMN_NAME = 'one_time_purchase'")->fetchColumn() > 0;
    if (!$hasOneTimePurchase) {
        $pdo->exec("ALTER TABLE webshop_items ADD COLUMN one_time_purchase TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_order");
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM webshop_items')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare('INSERT INTO webshop_items (name, description, image_url, price, category, commands, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $items = [
            ['VIP Tag', 'Unlock a shiny VIP tag in chat.', null, 500, 'Tags', ['lp user {player} permission set tag.vip true'], 10],
            ['Diamond Pack', 'A bundle of 16 diamonds delivered in game.', null, 250, 'Items', ['give {player} diamond 16'], 20],
            ['Golden Apple Pack', 'Receive 8 golden apples for your next adventure.', null, 300, 'Items', ['give {player} golden_apple 8'], 30],
            ['Cool Chat Color', 'Stand out with the cool chat color cosmetic.', null, 750, 'Cosmetics', ['lp user {player} permission set chatcolor.cool true'], 40],
        ];

        foreach ($items as $item) {
            $stmt->execute([$item[0], $item[1], $item[2], $item[3], $item[4], json_encode($item[5]), $item[6]]);
        }
    }
}

run_install();
