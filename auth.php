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

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_samesite' => 'Strict',
        'cookie_secure'   => isset($_SERVER['HTTPS']),
        'cookie_httponly' => true,
    ]);
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
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user_id;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));  // 🔒 rotate CSRF on login
    $pdo->prepare('UPDATE users SET last_login = NOW() WHERE user_id = ?')
        ->execute([$user_id]);
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
