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
 * @version    0.5.1.3-alpha
 * @see        /core/config/config.php, /core/auth/rate_limiter.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../session/SessionBootstrap.php';
require_once __DIR__ . '/../security/CsrfToken.php';
require_once __DIR__ . '/../auth/rate_limiter.php';
require_once __DIR__ . '/../dao/UserDAO.php';

use PixlKey\DAO\UserDAO;

// Start secure session
\PixlKey\Session\startSecureSession();

// Initialize DAO
$userDAO = new UserDAO($pdo);

// CSRF utilities are now handled by PixlKey\Security\CsrfToken
use function PixlKey\Security\generateToken as generate_csrf_token;
use function PixlKey\Security\validateToken as validate_csrf_token;
use function PixlKey\Security\rotateToken as rotate_csrf_token;

/* ---------------------------------------------------------------
   Login helpers
---------------------------------------------------------------- */
function login_user(string $user_id): void
{
    global $userDAO;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'login_' . $ip;

    if (too_many_attempts($rateKey, LOGIN_ATTEMPT_LIMIT, LOGIN_DECAY_SECONDS)) {
        rate_limit_exceeded_response(LOGIN_DECAY_SECONDS);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    \PixlKey\Security\rotateToken();
    $userDAO->updateLastLogin($user_id);

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
    global $userDAO;
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $cache = $userDAO->findById($_SESSION['user_id']);
    return $cache ?: null;
}

/**
 * Authenticate user by email and password.
 * Uses password_verify and password_needs_rehash for secure handling.
 */
function authenticate_user(string $email, string $password): ?array
{
    global $userDAO;
    $user = $userDAO->findByEmail($email);

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateKey = 'login_' . $ip;

    if (!$user || !password_verify($password, $user['password_hash'])) {
        record_failed_attempt($rateKey); // increment on failure
        return null;
    }

    // Rehash password if needed (algorithm upgrade, cost adjustment, etc.)
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        $userDAO->updatePasswordHash($user['user_id'], $newHash);
    }

    return $user;
}