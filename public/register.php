<?php

/**
 * register.php — Handles secure user registration, input validation, and login initiation
 *
 * PixlKey Project – Beta 0.5.0
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Accepts registration form input, enforces CSRF protection, rate limits abusive attempts,
 * hashes and stores passwords securely, prevents duplicate accounts, and logs in the user.
 * Implements session regeneration and rotates CSRF tokens on successful account creation.
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.2-alpha
 * @see        /core/auth/auth.php, /core/auth/rate_limiter.php, /core/helpers/functions.php
 */

require_once __DIR__ . '/../core/session/SessionBootstrap.php';
require_once __DIR__ . '/../core/auth/auth.php';
require_once __DIR__ . '/../core/security/CsrfToken.php';
require_once __DIR__ . '/../core/auth/rate_limiter.php';
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/helpers/functions.php';

\PixlKey\Session\startSecureSession();

// Alias CSRF helpers for local use
use function PixlKey\Security\generateToken as generate_csrf_token;
use function PixlKey\Security\validateToken as validate_csrf_token;
use function PixlKey\Security\rotateToken as rotate_csrf_token;

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf_token();

    $email    = strtolower(trim($_POST['email'] ?? ''));
    $display  = trim($_POST['display_name'] ?? '');
    $pwd      = $_POST['password'] ?? '';
    $confirm  = $_POST['password_confirm'] ?? '';

    // Centralized Rate Limiting Config
    $rateKey = 'register:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (RATE_LIMITING_ENABLED && too_many_attempts($rateKey, REGISTER_ATTEMPT_LIMIT, REGISTER_DECAY_SECONDS)) {
        rate_limit_exceeded_response(REGISTER_DECAY_SECONDS);
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid e-mail address.';
        }
        if ($pwd !== $confirm || strlen($pwd) < 8) {
            $errors[] = 'Passwords must match and be ≥ 8 characters.';
        }

        // unique e-mail check
        if (!$errors) {
            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
            $stmt->execute([$email]);
            if ($stmt->fetchColumn()) {
                $errors[] = 'This e-mail is already registered.';
            }
        }

        if (!$errors) {
            $stmt = $pdo->prepare(
                'INSERT INTO users (email,password_hash,display_name)
             VALUES (?,?,?)'
            );
            $stmt->execute([$email, password_hash($pwd, PASSWORD_DEFAULT), $display]);
            $userId = $pdo->lastInsertId() ?: $pdo->query(
                'SELECT user_id FROM users WHERE email = ' . $pdo->quote($email)
            )->fetchColumn();
            clear_failed_attempts($rateKey);

            // Prevent session fixation on registration
            session_regenerate_id(true);
            rotate_csrf_token();  // rotate CSRF on new login
            login_user($userId);
            header('Location: /index.php');
            exit;
        } else {
            record_failed_attempt($rateKey);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>Register | PixlKey</title>
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
        <h1>Create account</h1>
        <?php if ($errors): ?>
            <div class="error"><?= implode('<br>', array_map('htmlspecialchars', $errors)); ?></div>
        <?php endif; ?>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generate_csrf_token()) ?>">
        <label>E-mail <input type="email" name="email" required></label>
        <label>Display name <input type="text" name="display_name"></label>
        <label>Password <input type="password" name="password" required></label>
        <label>Confirm <input type="password" name="password_confirm" required></label>
        <button type="submit">Register</button>
        <p><a href="login.php">Have an account? Sign in</a></p>
    </form>
</body>

</html>