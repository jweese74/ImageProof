<?php

/**
 * logout.php — Securely terminates user sessions and rotates CSRF token post-logout
 *
 * PixlKey Project – Beta 0.5.0
 * Part of a secure PHP platform for managing digital artwork.
 *
 * Handles secure user logout by clearing session data, expiring cookies,
 * regenerating session identifiers, and rotating CSRF tokens to mitigate
 * fixation and replay attacks. Redirects users to the login screen.
 *
 * @package    PixlKey
 * @subpackage Public
 * @author     Jeffrey Weese
 * @copyright  2025 Jeffrey Weese | Infinite Muse Arts
 * @license    MIT
 * @version    0.5.1.3-alpha
 * @see        /public/login.php, /core/auth/auth.php
 */

+require_once __DIR__ . '/../core/config/config.php';
+require_once __DIR__ . '/../core/session/SessionBootstrap.php';
+require_once __DIR__ . '/../core/security/CsrfToken.php';

use function PixlKey\Security\rotateToken as rotate_csrf_token;

// Start session securely before making any changes
\PixlKey\Session\startSecureSession();


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

// Start fresh session after destroying old one
\PixlKey\Session\startSecureSession();
session_regenerate_id(true);

// Rotate CSRF token post-logout to prevent token replay
rotate_csrf_token();

// Redirect to login
header('Location: /public/login.php');
exit;
