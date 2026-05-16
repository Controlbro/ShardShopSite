<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/functions.php';

$user = current_user();
if ($user) {
    audit_log($user['uuid'], $user['username'], 'logout', 'Player logged out.');
}
logout_user();
redirect('/login.php');
