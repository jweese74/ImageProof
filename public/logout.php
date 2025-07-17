<?php
require_once __DIR__ . '/../core/auth/auth.php';
require_once __DIR__ . '/../core/config/config.php';

// Clear all session variables
session_unset();

// Destroy the session
session_destroy();

// Expire the session cookie immediately (if set via cookies)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"] ?? false, $params["httponly"] ?? true
    );
}

// 🔐 Start fresh session to prevent fixation reuse
session_start([
    'cookie_samesite' => 'Strict',
    'cookie_secure'   => isset($_SERVER['HTTPS']),
    'cookie_httponly' => true,
]);
session_regenerate_id(true);

// 🔐 Rotate CSRF token post-logout to prevent token replay
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

// Redirect to login
header('Location: /public/login.php');
exit;
