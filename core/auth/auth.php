<?php

/**
 * auth.php — Manages authentication, session security, and CSRF protection
 *
 * PixlKey Project – Beta 0.5.0  
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Handles login sessions, user identity retrieval, and form protection via CSRF tokens.
 * Enforces rate limiting on login attempts and applies secure cookie/session policies.
 *
 * @package    PixlKey
 * @subpackage Core\Auth
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.2-alpha
 * @see        /core/config/config.php, /core/auth/rate_limiter.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session/SessionBootstrap.php';
require_once __DIR__ . '/../security/CsrfToken.php';
require_once __DIR__ . '/rate_limiter.php';

// Start secure session
\PixlKey\Session\startSecureSession();

// CSRF utilities are now handled by PixlKey\Security\CsrfToken
use function PixlKey\Security\generateToken as generate_csrf_token;
use function PixlKey\Security\validateToken as validate_csrf_token;
use function PixlKey\Security\rotateToken as rotate_csrf_token;

/* ---------------------------------------------------------------
   Login helpers
---------------------------------------------------------------- */
function login_user(string $user_id): void
{
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'login_' . $ip;

    if (too_many_attempts($rateKey, LOGIN_ATTEMPT_LIMIT, LOGIN_DECAY_SECONDS)) {
        rate_limit_exceeded_response(LOGIN_DECAY_SECONDS);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    \PixlKey\Security\rotateToken();
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
        ->execute([$user_id]);

    clear_failed_attempts($rateKey);
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        $dest = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: /public/login.php?next=' . $dest);
        exit;
    }
}

function current_user(): ?array
{
    global $pdo;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $stmt  = $pdo->prepare(
        'SELECT user_id,email,display_name,is_admin FROM users WHERE user_id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $cache = $stmt->fetch();
    return $cache ?: null;
}

/**
 * Authenticate user by email and password.
 * Uses password_verify and password_needs_rehash for secure handling.
 */
function authenticate_user(string $email, string $password): ?array
{
    global $pdo;
    $stmt = $pdo->prepare('SELECT user_id, password_hash FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'login_' . $ip;

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_attempt($rateKey); // increment on failure
        return null;
    }

    // Rehash password if needed (algorithm upgrade, cost adjustment, etc.)
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $update = $pdo->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?');
        $update->execute([$newHash, $user['user_id']]);
    }

    return $user;
}
