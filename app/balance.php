<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function sql_identifier(string $identifier): string
{
    if (!preg_match('/^[A-Za-z0-9_]+$/', $identifier)) {
        throw new RuntimeException('Invalid balance configuration identifier: ' . $identifier);
    }

    return '`' . $identifier . '`';
}

function balance_config_error(Throwable $exception): RuntimeException
{
    return new RuntimeException('Unable to read Shards balance. Check BALANCE_SOURCE, BALANCE_TABLE, and balance column settings in app/config.php. Details: ' . $exception->getMessage());
}

function get_balance(string $uuid): int
{
    $pdo = db();

    try {
        if (BALANCE_SOURCE === 'webshop_fallback') {
            $stmt = $pdo->prepare('SELECT shards FROM webshop_balances WHERE uuid = ? LIMIT 1');
            $stmt->execute([$uuid]);
            $row = $stmt->fetch();
            return $row ? (int) $row['shards'] : 0;
        }

        if (BALANCE_SOURCE !== 'plugin') {
            throw new RuntimeException('BALANCE_SOURCE must be "plugin" or "webshop_fallback".');
        }

        $table = sql_identifier(BALANCE_TABLE);
        $uuidColumn = sql_identifier(BALANCE_UUID_COLUMN);
        $currencyColumn = sql_identifier(BALANCE_CURRENCY_COLUMN);
        $amountColumn = sql_identifier(BALANCE_AMOUNT_COLUMN);
        $stmt = $pdo->prepare("SELECT $amountColumn AS balance_amount FROM $table WHERE $uuidColumn = ? AND $currencyColumn = ? LIMIT 1");
        $stmt->execute([$uuid, BALANCE_CURRENCY_VALUE]);
        $row = $stmt->fetch();

        return $row ? (int) $row['balance_amount'] : 0;
    } catch (Throwable $exception) {
        throw balance_config_error($exception);
    }
}

function lock_balance_for_update(PDO $pdo, string $uuid, string $username): int
{
    try {
        if (BALANCE_SOURCE === 'webshop_fallback') {
            $insert = $pdo->prepare('INSERT INTO webshop_balances (uuid, username, shards) VALUES (?, ?, 0) ON DUPLICATE KEY UPDATE username = VALUES(username)');
            $insert->execute([$uuid, $username]);

            $stmt = $pdo->prepare('SELECT shards FROM webshop_balances WHERE uuid = ? LIMIT 1 FOR UPDATE');
            $stmt->execute([$uuid]);
            $row = $stmt->fetch();
            return $row ? (int) $row['shards'] : 0;
        }

        if (BALANCE_SOURCE !== 'plugin') {
            throw new RuntimeException('BALANCE_SOURCE must be "plugin" or "webshop_fallback".');
        }

        $table = sql_identifier(BALANCE_TABLE);
        $uuidColumn = sql_identifier(BALANCE_UUID_COLUMN);
        $currencyColumn = sql_identifier(BALANCE_CURRENCY_COLUMN);
        $amountColumn = sql_identifier(BALANCE_AMOUNT_COLUMN);
        $stmt = $pdo->prepare("SELECT $amountColumn AS balance_amount FROM $table WHERE $uuidColumn = ? AND $currencyColumn = ? LIMIT 1 FOR UPDATE");
        $stmt->execute([$uuid, BALANCE_CURRENCY_VALUE]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new RuntimeException('No plugin balance row found for this player/currency. Update app/config.php or switch BALANCE_SOURCE to webshop_fallback.');
        }

        return (int) $row['balance_amount'];
    } catch (Throwable $exception) {
        throw balance_config_error($exception);
    }
}

function subtract_locked_balance(PDO $pdo, string $uuid, string $username, int $amount): void
{
    try {
        if (BALANCE_SOURCE === 'webshop_fallback') {
            $stmt = $pdo->prepare('UPDATE webshop_balances SET shards = shards - ?, username = ? WHERE uuid = ?');
            $stmt->execute([$amount, $username, $uuid]);
            return;
        }

        $table = sql_identifier(BALANCE_TABLE);
        $uuidColumn = sql_identifier(BALANCE_UUID_COLUMN);
        $currencyColumn = sql_identifier(BALANCE_CURRENCY_COLUMN);
        $amountColumn = sql_identifier(BALANCE_AMOUNT_COLUMN);
        $stmt = $pdo->prepare("UPDATE $table SET $amountColumn = $amountColumn - ? WHERE $uuidColumn = ? AND $currencyColumn = ?");
        $stmt->execute([$amount, $uuid, BALANCE_CURRENCY_VALUE]);
    } catch (Throwable $exception) {
        throw balance_config_error($exception);
    }
}
