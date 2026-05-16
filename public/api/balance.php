<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/functions.php';
require_once __DIR__ . '/../../app/balance.php';

$user = require_login_json();
try {
    $balance = get_balance($user['uuid']);
    json_response(['success' => true, 'balance' => $balance, 'formatted' => format_shards($balance)]);
} catch (Throwable $exception) {
    json_response(['success' => false, 'error' => $exception->getMessage()], 500);
}
