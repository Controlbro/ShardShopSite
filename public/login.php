<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/install.php';
require_once __DIR__ . '/../app/functions.php';

if (is_logged_in()) {
    redirect('/shop.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if ($username === '' || $password === '') {
            throw new RuntimeException('Enter your Minecraft username and shop password.');
        }
        if (login_user($username, $password)) {
            $user = current_user();
            audit_log($user['uuid'], $user['username'], 'login_success', 'Player logged in.');
            redirect('/shop.php');
        }
        audit_log(null, $username, 'login_failed', 'Invalid username or password.');
        $error = 'Invalid username or password.';
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

render_header('Login');
?>
<section class="auth-wrap">
    <form class="auth-card" method="post" action="/login.php">
        <?= csrf_field() ?>
        <p class="eyebrow">Player Login</p>
        <h1>Welcome back</h1>
        <p>Use your Minecraft username and password created in game with <strong>/shop</strong>.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <label>Minecraft Username<input type="text" name="username" maxlength="16" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn full" type="submit">Login</button>
    </form>
</section>
<?php render_footer(); ?>
