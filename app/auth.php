<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function current_user(): ?array
{
    start_secure_session();
    if (empty($_SESSION['user']['uuid']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    return [
        'uuid' => (string) $_SESSION['user']['uuid'],
        'username' => (string) $_SESSION['user']['username'],
    ];
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): array
{
    $user = current_user();
    if ($user === null) {
        header('Location: /login.php');
        exit;
    }

    return $user;
}

function login_user(string $username, string $password): bool
{
    $stmt = db()->prepare('SELECT uuid, username, password_hash FROM shop_accounts WHERE LOWER(username) = LOWER(?) LIMIT 1');
    $stmt->execute([$username]);
    $account = $stmt->fetch();

    if (!$account || !password_verify($password, (string) $account['password_hash'])) {
        return false;
    }

    start_secure_session();
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'uuid' => (string) $account['uuid'],
        'username' => (string) $account['username'],
    ];

    return true;
}

function logout_user(): void
{
    start_secure_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}
