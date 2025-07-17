<?php

/**
 * Session / Auth / CSRF helpers
 * ---------------------------------------------------------------
 *  • Starts a secure session.
 *  • Exposes:  generate_csrf_token(), validate_csrf_token()
 *              login_user(), require_login(), current_user()
 */

declare(strict_types=1);
require_once __DIR__ . '/config.php';

require_once __DIR__ . '/rate_limiter.php';

// Enforce secure cookie flags globally (before session_start)
ini_set('session.cookie_secure', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Strict');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ]);
    session_start();
}

/* ---------------------------------------------------------------
   CSRF
---------------------------------------------------------------- */
function generate_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        return;                             // nothing to check
    }
    $good = $_SESSION['csrf_token'] ?? '';
    $sent = $_POST['csrf_token']
        ?? $_SERVER['HTTP_X_CSRFTOKEN']
        ?? '';
    if (!hash_equals($good, $sent)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}

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
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  // 🔒 rotate CSRF on login
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
        ->execute([$user_id]);

    clear_failed_attempts($rateKey); // ✅ reset on successful login
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        $dest = urlencode($_SERVER['REQUEST_URI'] ?? '/');
        header('Location: login.php?next=' . $dest);
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
        record_failed_attempt($rateKey); // ❌ increment on failure
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
