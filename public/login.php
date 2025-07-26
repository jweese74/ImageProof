<?php

/**
 * login.php — Authenticates users and initiates secure login sessions
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * This controller handles user login by verifying credentials,
 * enforcing CSRF protection, and applying rate limiting to
 * mitigate brute-force attacks. On success, it establishes a
 * secure session and redirects the user accordingly.
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.1-alpha
 * @see        /core/auth/auth.php, /core/auth/rate_limiter.php, /core/session/SessionBootstrap.php
 */

require_once __DIR__ . '/../core/session/SessionBootstrap.php';
require_once __DIR__ . '/../core/auth/auth.php';
require_once __DIR__ . '/../core/security/CsrfToken.php';
require_once __DIR__ . '/../core/auth/rate_limiter.php';
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/helpers/functions.php';

\PixlKey\Session\startSecureSession();

// Alias CSRF helpers for form usage
use function PixlKey\Security\generateToken as generate_csrf_token;
use function PixlKey\Security\validateToken as validate_csrf_token;
use function PixlKey\Security\rotateToken as rotate_csrf_token;

$next   = $_GET['next']
       ?? ($_POST['next'] ?? '/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $email = strtolower(trim($_POST['email'] ?? ''));
    $pwd   = $_POST['password'] ?? '';

    // Match back-end bucket name so the same counter is shared everywhere
    $rateKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (RATE_LIMITING_ENABLED && too_many_attempts($rateKey, LOGIN_ATTEMPT_LIMIT, LOGIN_DECAY_SECONDS)) {
        // Optional: respond with HTTP 429 instead of HTML form error
        // rate_limit_exceeded_response(LOGIN_DECAY_SECONDS);
        $errors[] = 'Too many failed login attempts. Please wait before trying again.';
    } else {
        $stmt = $pdo->prepare('SELECT user_id,password_hash FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $u = $stmt->fetch();

        if (!$u || !password_verify($pwd, $u['password_hash'])) {
            $errors[] = 'Invalid e-mail or password.';
            record_failed_attempt($rateKey);
        } else {
            // Rehash password if outdated
            if (password_needs_rehash($u['password_hash'], PASSWORD_DEFAULT)) {
                $newHash = password_hash($pwd, PASSWORD_DEFAULT);
                $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
                $update->execute([$newHash, $u['user_id']]);
            }
            clear_failed_attempts($rateKey);
            login_user($u['user_id']);
            header('Location: ' . $next);
            exit;
        }
    } // end rate limit check
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Log in | PixlKey</title>
    <style>
        body {
            font-family: sans-serif;
            background: #111;
            color: #eee
        }

        form {
            max-width: 420px;
            margin: 4em auto;
            padding: 2em;
            background: #222;
            border-radius: 8px
        }

        input,
        button {
            width: 100%;
            padding: .6em;
            margin: .4em 0;
            background: #333;
            border: 1px solid #444;
            color: #eee
        }

        .error {
            color: #e74c3c
        }
    </style>
</head>

<body>
    <form method="post" novalidate>
        <h1>Sign in</h1>
        <?php if ($errors): ?>
            <div class="error"><?= implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <input type="hidden" name="next" value="<?= htmlspecialchars($next) ?>">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Password <input type="password" name="password" required></label>
        <button type="submit">Log in</button>
        <p><a href="register.php">Need an account? Register</a></p>
    </form>
</body>

</html>
