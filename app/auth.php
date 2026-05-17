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

function remember_cookie_secure(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
}

function remember_cookie_options(int $expires): array
{
    return [
        'expires' => $expires,
        'path' => '/',
        'domain' => '',
        'secure' => remember_cookie_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function remember_cookie_signature(string $uuid, string $username, int $expires): string
{
    return hash_hmac('sha256', $uuid . '|' . $username . '|' . $expires, WEBSHOP_API_KEY);
}

function set_remember_cookie(string $uuid, string $username): void
{
    $expires = time() + REMEMBER_COOKIE_LIFETIME;
    $payload = [
        'uuid' => $uuid,
        'username' => $username,
        'expires' => $expires,
        'signature' => remember_cookie_signature($uuid, $username, $expires),
    ];

    setcookie(REMEMBER_COOKIE_NAME, base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES)), remember_cookie_options($expires));
}

function clear_remember_cookie(): void
{
    setcookie(REMEMBER_COOKIE_NAME, '', remember_cookie_options(time() - 42000));
}

function user_from_remember_cookie(): ?array
{
    $cookie = (string) ($_COOKIE[REMEMBER_COOKIE_NAME] ?? '');
    if ($cookie === '') {
        return null;
    }

    $json = base64_decode($cookie, true);
    if ($json === false) {
        clear_remember_cookie();
        return null;
    }

    $payload = json_decode($json, true);
    if (!is_array($payload)) {
        clear_remember_cookie();
        return null;
    }

    $uuid = (string) ($payload['uuid'] ?? '');
    $username = (string) ($payload['username'] ?? '');
    $expires = (int) ($payload['expires'] ?? 0);
    $signature = (string) ($payload['signature'] ?? '');

    if ($uuid === '' || $username === '' || $expires < time()) {
        clear_remember_cookie();
        return null;
    }

    $expected = remember_cookie_signature($uuid, $username, $expires);
    if (!hash_equals($expected, $signature)) {
        clear_remember_cookie();
        return null;
    }

    $stmt = db()->prepare('SELECT uuid, username FROM shop_accounts WHERE uuid = ? LIMIT 1');
    $stmt->execute([$uuid]);
    $account = $stmt->fetch();
    if (!$account) {
        clear_remember_cookie();
        return null;
    }

    start_secure_session();
    $_SESSION['user'] = [
        'uuid' => (string) $account['uuid'],
        'username' => (string) $account['username'],
    ];

    return $_SESSION['user'];
}

function current_user(): ?array
{
    start_secure_session();
    if (empty($_SESSION['user']['uuid']) || empty($_SESSION['user']['username'])) {
        $rememberedUser = user_from_remember_cookie();
        if ($rememberedUser === null) {
            return null;
        }
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
    set_remember_cookie((string) $account['uuid'], (string) $account['username']);

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

    clear_remember_cookie();
    session_destroy();
}
