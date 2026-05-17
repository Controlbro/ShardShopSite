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
            redirect('/shop.php?welcome=1');
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
        <p>Use your Minecraft username and password created in game with <strong>/shardshop</strong>. Successful logins are securely saved in your browser cookies for 30 days.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <label>Minecraft Username<input type="text" name="username" maxlength="16" required autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn full" type="submit">Login</button>
        <div class="auth-help-grid" aria-label="Account help">
            <details class="auth-help">
                <summary>Forgot password?</summary>
                <p>Join the server in Minecraft and run <code>/shardshop resetpassword</code> to create a fresh shop password.</p>
            </details>
            <details class="auth-help">
                <summary>Need to sign up?</summary>
                <p>Join the server in Minecraft and run <code>/shardshop</code> to make your web shop account.</p>
            </details>
        </div>
    </form>
</section>
<?php render_footer(); ?>
