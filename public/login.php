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
 * @version    0.5.1.4-alpha
 * @see        /core/auth/auth.php, /core/auth/rate_limiter.php, /core/session/SessionBootstrap.php
 */

require_once __DIR__ . '/../core/session/SessionBootstrap.php';
require_once __DIR__ . '/../core/auth/auth.php';
require_once __DIR__ . '/../core/security/CsrfToken.php';
require_once __DIR__ . '/../core/auth/rate_limiter.php';
require_once __DIR__ . '/../core/config/config.php';
require_once __DIR__ . '/../core/helpers/functions.php';
require_once __DIR__ . '/../core/dao/UserDAO.php';

\PixlKey\Session\startSecureSession();

// Initialize AuthService (already created in auth.php)
global $authService;

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

    // All logic is encapsulated in AuthService via authenticate_user()
    $user = authenticate_user($email, $pwd);
    if ($user) {
        header('Location: ' . $next);
        exit;
    } else {
        // AuthService will handle rate limiting, but you may still want to show an error for UI consistency
        $errors[] = 'Invalid e-mail or password, or you have exceeded login attempts. Please wait and try again.';
    }
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